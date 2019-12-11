<?php

namespace Vent;

interface iVent {
    // Sets vent position, approximate % airflow
    public function setOpen($percent);
}
