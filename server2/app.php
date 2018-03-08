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
        // This is not the best implementation. Need to figure out something better.
        if ($masterMode == iThermostat::MODE_OFF) {
            // We need to close master thermostat vent, and open all others
            $ventTarget[$this->masterZoneId] = 0;
            $this->log->addDebug("Thermostat in auto mode, closing master, and leaving others open.");
            $this->executeVentMoves($ventTarget);
            // Nothing more to do here
            return;
        }

        // Check if overrides have been canceled out by some external action
        if ($this->state->override->present && $master->getChecksum() != $this->state->master_checksum) {
            $this->state->override->present = false;
            $this->state->master_checksum = '';
        }

        // Only calls matching mode would be respected
        $zonesOpen = 0; // increment each time we have zone that we don't want to close, except master
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
            }
        }
        $this->log->addDebug("Number of zones to open: " . $zonesOpen);
        // See if we want to override master zone (not supported in "auto" mode, as heat/cool difference might collide)
        // Also need to make sure we've been uninterrupted with state file for long enough.
        $this->log->addDebug("Time since init of state: " . (time() - $this->state->init_time));
        if ($masterMode != iThermostat::MODE_AUTO
            && $zonesOpen > 0
            && $master->getCall() == iThermostat::MODE_OFF
            && (time() - $this->state->init_time) > $this->appConfig->override_activate_time
        ) {
            // Override master by threshold
            $this->log->addInfo("Setting override for master zone");
            $master->setOverride();
            $this->state->override->present = true;
            $this->state->master_checksum = $master->getChecksum();
        }
        if ($zonesOpen == 0 && $this->state->override->present) {
            // remove master override
            // keep all zones open?
            $this->log->addInfo("Removing override for master zone");
            $master->removeOverride();
            $this->state->override->present = false;
            $this->state->master_checksum = $master->getChecksum();
        }
        // Close master zone if some others are open
        if ($zonesOpen > 0) {
            $ventTarget[$this->masterZoneId] = 0;
        }
        // Execute all moves
        $this->executeVentMoves($ventTarget);
        $this->saveState();
    }

    private function initState() {
        $file = getenv('STATE_FILE');
        $this->log->addDebug("State file path: " . $file);
        if (!empty($file) && is_readable($file)) {
            $state = json_decode(file_get_contents($file));
            $this->log->addDebug("Time since state update: " . (time()-$state->last_update));
            $this->log->addDebug("state expiration: " . ($this->appConfig->state_expiration));
            if (empty($state) // Don't have a valid state file
                || empty($state->last_update) // State last update is unknown
                || (time()-$state->last_update) > $this->appConfig->state_expiration // Last state update is too old
            ) {
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
            'override' => (object)[
                'present' => false,
            ]
        ];
    }

    private function saveState() {
        $file = getenv('STATE_FILE');
        if (empty($file)) {
            return;
        }
        $this->state->last_update = time();
        file_put_contents($file, json_encode($this->state, true));
    }

    private function initEquipment() {
        foreach ($this->zoneConfig as $id=>$zone) {
            $adapter = \Thermostat\Factory::get($zone->thermostat);
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
        $this->log->addDebug("Vent targets: ", $ventTarget);
        $lastException = null;
        foreach ($ventTarget as $id=>$percent) {
            foreach ($this->ventInstance[$id] as $vent) {
                try {
                    $vent->setOpen($percent);
                    sleep($delay);
                } catch (\Exception $e) {
                    // Catching all exceptions, as we need to execute all of the moves, even if some don't work.
                    $lastException = $e;
                }
            }
        }
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
