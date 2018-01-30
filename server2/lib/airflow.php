<?php

// Library to enforce minimum airflow
class Airflow {
    // Target vent states (percent open) per zone [zoneId => 0-100, ...]
    private $target;
    // Zones
    private $zones;
    // zone that we want to control only after all others are 100% open
    private $masterZoneId;
    // Airflow summary
    private $openAirflow = 0;
    private $availableOtherAirflow = 0;
    private $availableMasterAirflow = 0;

    public function __construct($ventTarget) {
        $this->target = $ventTarget;
    }

    // Adding a zone to configuration, and keeping running totals for airlow
    public function addZone($id, $airflow, $master=false) {
        if (!empty($this->zones[$id])) {
            throw new Exception("Zone {$id} is already configured");
        }
        if (!isset($this->target[$id])) {
            throw new Exception("Unknown zone ({$id}). Not listed in vent target data.");
        }
        if ($airflow < 0) {
            throw new Exception("Zone {$id} can't have negative airflow {$airflow}");
        }
        $this->zones[$id] = $airflow;
        if (!empty($this->masterZoneId) && $master === true) {
            throw new Exception("Already have master zone ({$this->masterZoneId}), but new one is supplied ({$id})");
        }
        // Open and available airflow calculations
        $this->openAirflow += $airflow*$this->target[$id]/100;
        if ($master === true) {
            $this->masterZoneId = $id;
            $this->availableMasterAirflow += (100-$this->target[$id])*$airflow/100;
        } else {
            $this->availableOtherAirflow += (100-$this->target[$id])*$airflow/100;
        }
    }

    // Calculate what needs to be changed, and modify vent openings to enforce minimum airflow
    public function getEnforced($minAirflow) {
        if ($this->openAirflow >= $minAirflow) {
            return $this->target;
        }
        $needed = $minAirflow - $this->openAirflow;
        $otherToAdd = min(100, (int) 100 * $needed / $this->availableOtherAirflow); // in percent
        $masterToAdd = 0;
        if ($otherToAdd >= 100 && $this->availableMasterAirflow > 0) {
            $masterToAdd = min(
                100-$this->target[$this->masterZoneId],
                (int) 100 * ($needed - $this->availableOtherAirflow) / $this->availableMasterAirflow
            );
        }
        $modifiedTarget = $this->target;
        $modifiedTarget[$this->masterZoneId] += $masterToAdd;
        foreach ($this->zones as $id=>$airflow) {
            if ($id == $this->masterZoneId) {
                // We took care of master zone above
                continue;
            }
            $modifiedTarget[$id] += (100 - $this->target[$id]) * $otherToAdd/100;
        }
        return $modifiedTarget;
    }
}
