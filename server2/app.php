<?php

use \Thermostat\iThermostat;
use Monolog\Logger;

class App {
    // List of thermostats and full configuration
    private $zoneConfig;
    // General app configuration;
    private $appConfig;
    // thermostat adapters using iThermostat
    private $tstatInstance;
    // Master zone id (one whose thermostat is connected to equipment)
    private $masterZoneId;
    // All vents by thermostat ID
    private $ventInstance;
    // Logger instance
    private $log;
    // State persistence
    private $state;

    // Tstats file should have a json definition for all room tstats and their vents
    public function __construct($configFile, Logger $log) {
        $this->log = $log;
        $config = json_decode(file_get_contents(__DIR__ . '/' . $configFile));
        if (empty($config)) {
            throw new Exception('Invalid zone JSON file.');
        }
        $this->zoneConfig = $config->zones;
        $this->appConfig = $config->parameters;
    }

    public function run() {
        $this->initEquipment();
        $this->initState();
        // Target vent positions, initialize all to open
        $ventTarget = [];
        foreach ($this->tstatInstance as $id=>$tstat) {
            $ventTarget[$id] = 100;
        }
        $master = $this->tstatInstance[$this->masterZoneId];
        $masterMode = $master->getMode();
        // Override mode to the call type, if it's in auto, as that's the only surefire way to figure out what's going to happen
        if ($masterMode == iThermostat::MODE_AUTO) {
            $masterMode = $master->getCall();
        }
        // If master mode is auto switching, then we don't know what'll happen until master makes a call

        if ($masterMode == iThermostat::MODE_OFF) {
            // We need to close master thermostat vent, and open all others
            $ventTarget[$this->masterZoneId] = 0;
            $this->log->addDebug("Thermostat in auto mode, closing master, and leaving others open.");
            $this->executeVentMoves($ventTarget);
            // Nothing more to do here
            return;
        }

        // Only calls matching mode would be respected
        $zonesOpen = 0; // increment each time we have zone that we don't want to close, except master
        $zonesOpenNames = [];
        foreach ($this->tstatInstance as $id=>$tstat) {
            // Skipping master
            if ($this->masterZoneId == $id) {
                continue;
            }
            // if call type does not match master mode, then we're closing this zone
            if ($tstat->getCall() != $masterMode) {
                $ventTarget[$id] = 0;
            } else {
                // Should be already 100, as it was initialized, but just in case...
                $ventTarget[$id] = 100;
                $zonesOpen++;
                $zonesOpenNames[] = $this->zoneConfig[$id]->name;
            }
        }
        $this->log->addDebug("Number of non-master zones to open: ", $zonesOpenNames);
        // See if we want to override master zone (not supported in "auto" mode, as heat/cool difference might collide)
        // Also need to make sure we've been uninterrupted with state file for long enough.
        do {
            if ($zonesOpen == 0) {
                // No additional zones are open, so override is not necessary
                break;
            }
            if ($master->getCall() != iThermostat::MODE_OFF) {
                // Master zone is already on, so no override necessary.
                break;
            }
            if ($this->state->override_present) {
                // Override was already set, so no need to do anything.
                $this->log->addDebug("Override was already set at {$this->state->override_set_time}. Not adding another one");
                break;
            }
            if ($masterMode == iThermostat::MODE_AUTO) {
                $this->log->addDebug("Overrides are not supported in auto mode");
                break;
            }
            if ((time() - $this->state->init_time) < $this->appConfig->override_activate_time) {
                $this->log->addDebug("State data is too new, needs to work for a bit longer to be sure.");
                break;
            }
            // Override master by threshold
            $this->log->addInfo("Setting override for master zone");
            $master->setOverride();
            $this->state->override_present = true;
            $this->state->master_checksum = $master->getChecksum();
            $this->state->override_set_time = date('Y-m-d H:i:s');
        } while (false); // run above code only once, so that we can use breaks for more readability
        // Override flag will not be removed until all zones reach target temperatures
        // If temperature was overwritten in the meantime (e.g. override rolled back by user), we'll not try again
        if ($zonesOpen == 0 && $this->state->override_present) {
            // remove master override
            // keep all zones open?
            if ($this->state->master_checksum == $master->getChecksum()) {
                $this->log->addInfo("Setting temperature back to remove override.");
                $master->removeOverride();
            } else {
                $this->log->addInfo("Thermostat data was externally modified. Override flag removed, but temperature not changed.");
            }
            $this->state->override_present = false;
            $this->state->master_checksum = $master->getChecksum();
            $this->state->override_set_time = '';
        } else { // NOTE: vent moves will not be executed if we've removed the override. This is to prevent conditioning master zone for an extra minute.
            // Close master zone if some others are open
            if ($zonesOpen > 0) {
                $ventTarget[$this->masterZoneId] = 0;
            }
            // If all non-master zones are closed, while master mode is "off" - revert all vents to default position for recirculation
            if ($zonesOpen == 0 && $master->getCall() == iThermostat::MODE_OFF) {
                $this->log->addInfo("Everything's off, moving to default positions to allow for recirculation");
                $ventTarget = $this->getDefaultVentTarget(); // full override
            }
            // Execute all moves
            $this->executeVentMoves($ventTarget);
        }
        $this->saveState();
    }

    private function getDefaultVentTarget() {
        $ventTarget = [];
        foreach ($this->zoneConfig as $id=>$zone) {
            // When not set, leave fully open
            $ventTarget[$id] = (empty($zone->defaultOpen) ? 100 : $zone->defaultOpen);
        }
        return $ventTarget;
    }

    private function initState() {
        $file = getenv('STATE_FILE');
        if (!empty($file) && is_readable($file)) {
            $state = json_decode(file_get_contents($file));
            if (empty($state) // Don't have a valid state file
                || empty($state->last_update) // State last update is unknown
                || (time()-$state->last_update) > $this->appConfig->state_expiration // Last state update is too old
            ) {
                $this->log->addInfo("Invalid state file or it's been too long since update. Reinit to empty.");
                $this->state = $this->getEmptyState();
                return;
            }
            $this->state = $state;
            return;
        }
        $this->log->addDebug("State file is not present");
        $this->state = $this->getEmptyState();
        return;
    }

    // Builds a state construct, when we don't have any, or when it's expired
    private function getEmptyState() {
        return (object)[
            'last_update' => time(),
            // Indicates how long we've had uninterrupted operation
            'init_time' => time(),
            'master_checksum' => '',
            'override_present' =>  false,
        ];
    }

    private function saveState() {
        $file = getenv('STATE_FILE');
        if (empty($file)) {
            return;
        }
        $this->state->last_update = time();
        file_put_contents($file, json_encode($this->state, JSON_PRETTY_PRINT));
    }

    private function initEquipment() {
        foreach ($this->zoneConfig as $id=>$zone) {
            $adapter = \Thermostat\Factory::get($zone->thermostat);
//            $this->log->addDebug("Seconds since check-in for {$id}: " . $adapter->getSecondsSinceLastUpdate());
            $this->tstatInstance[$id] = $adapter;
            if (!empty($zone->master) && $zone->master === true) {
                if (!empty($this->masterZoneId)) {
                    throw new Exception("Cannot have more than 1 master zone. ({$this->masterZoneId} and {$id} found)");
                }
                $this->masterZoneId = $id;
            }
            $this->ventInstance[$id] = [];
            // Assign all vents to this thermostat
            if (!empty($zone->vents)) {
                foreach ($zone->vents as $vent) {
                    $this->ventInstance[$id][] = VentFactory::get($vent);
                }
            }
        }
        if (empty($this->masterZoneId)) {
            throw new Exception("Must have exactly one master zone (one that's connected to equipment), but none found in config.");
        }
    }

    // Sorts all target states from most open to most closed, and executes all moves
    private function executeVentMoves($ventTarget, $delay=2) {
        // sorting all zones
        $ventTarget = $this->enforceMinAirflow($ventTarget);
        arsort($ventTarget);
        $lastException = null;
        $ventTargetsText = "";
        foreach ($ventTarget as $id=>$percent) {
            $ventTargetsText .= $this->zoneConfig[$id]->name . " = $percent%; ";
            foreach ($this->ventInstance[$id] as $vent) {
                try {
                    $vent->setOpen($percent);
                    sleep($delay);
                } catch (\Exception $e) {
                    $this->log->addError("Exception moving vent {$id}");
                    // Catching all exceptions, as we need to execute all of the moves, even if some don't work.
                    $lastException = $e;
                }
            }
        }
        $this->log->addDebug($ventTargetsText);
        if (!empty($lastException)) {
            throw $lastException;
        }
    }

    // min_airflow enforcement
    private function enforceMinAirflow($ventTarget) {
        $flow = new Airflow($ventTarget);
        foreach ($ventTarget as $id=>$percent) {
            $flow->addZone($id, $this->zoneConfig->{$id}->airflow, ($id==$this->masterZoneId));
        }
        return $flow->getEnforced($this->appConfig->min_airflow);
    }

    // Reusable function to get environment variable, and throw an exception if none found
    // (as opposed to returning false by default)
    public static function getRequiredEnv($var) {
        $data = getenv($var);
        if (empty($data)) {
            throw new Exception("Please specify $var as environment variable to run.");
        }
        return $data;
    }
}
