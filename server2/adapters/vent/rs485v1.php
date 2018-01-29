<?php

namespace Vent;

class RS485v1 implements iVent {
    // Vent connection information
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    private function validateConfig() {
        if (empty($this->config->device)) {
            throw new \Exception("Error instantiating vent. No device set.");
        }
    }

    public function setOpen($percent) {
        if ($percent < 0) {
            $percent = 0;
        }
        if ($percent > 100) {
            $percent = 100;
        }
        // Convert percent to approximate degrees for rotating vent
        $degrees = (int)rad2deg(asin($percent/100));
        echo "Setting vent {$this->config->device} to {$degrees}deg.\n";
        throw new \Exception("not yet implemented");
    }
}
