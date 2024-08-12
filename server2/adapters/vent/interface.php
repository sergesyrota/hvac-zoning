<?php

namespace Vent;

interface iVent {
    // Sets vent position, approximate % airflow
    public function setOpen($percent);

    // Returns true if vent is in error state
    public function errorPresent();

    // Returns error reason if errorPresent() is true; Null otherwise
    public function errorReason();

    // In case we're in the error state, try to self-heal by recalibrating
    public function selfHeal();

    // Returns human-readable name of the vent
    public function getHumanReadableName();
}
