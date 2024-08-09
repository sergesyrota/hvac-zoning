<?php

namespace Vent;

interface iVent {
    // Sets vent position, approximate % airflow
    public function setOpen($percent);

    // Returns true if vent is in error state
    public function errorPresent();

    // Returns error reason if errorPresent() is true; Null otherwise
    public function errorReason();
}
