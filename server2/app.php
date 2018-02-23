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

    // Tstats file should have a json definition for all room tstats and their vents
    public function __construct($configFile, Logger $log) {
        $this->log = $log;
        $config = json_decode(file_get_contents($configFile));
        if (empty($config)) {
            throw new Exception('Invalid zone JSON file.');
        }
        $this->zoneConfig = $config->zones;
        $this->appConfig = $config->parameters;
    }

    public function run() {
        $this->initEquipment();
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
        // If master is not actively heating or cooling, leave all vents open
        if ($master->getCall() == iThermostat::MODE_OFF) {
            $this->log->addDebug("Not actively doing anything. Leaving vents open.");
            $this->executeVentMoves($ventTarget);
            return;
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
        // Close master zone if some others are open
        if ($zonesOpen > 0) {
            $ventTarget[$this->masterZoneId] = 0;
        }
        // Execute all moves
        $this->executeVentMoves($ventTarget);
    }

    private function initEquipment() {
        foreach ($this->zoneConfig as $id=>$zone) {
            $adapter = ThermostatFactory::get($zone->thermostat);
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
        foreach ($ventTarget as $id=>$percent) {
            foreach ($this->ventInstance[$id] as $vent) {
                $vent->setOpen($percent);
                sleep($delay);
            }
        }
    }

    // minAirflow enforcement
    private function enforceMinAirflow($ventTarget) {
        $flow = new Airflow($ventTarget);
        foreach ($ventTarget as $id=>$percent) {
            $flow->addZone($id, $this->zoneConfig->{$id}->airflow, ($id==$this->masterZoneId));
        }
        return $flow->getEnforced($this->appConfig->minAirflow);
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
