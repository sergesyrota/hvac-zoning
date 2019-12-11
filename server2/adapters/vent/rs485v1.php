<?php

namespace Vent;

class RS485v1 implements iVent {
    // Vent connection information
    private $config;
    // Automation protocol library
    private $gm;

    public function __construct($config, \SyrotaAutomation\Gearman $gm) {
        $this->config = $config;
        $this->gm = $gm;
        $this->validateConfig();
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
        $res = $this->gm->command($this->config->device, sprintf('setDegrees:%d', $degrees));
        if ($res != 'Working') {
            throw new \Exception(sprintf("Unexpected response from vent %s: '%s'", $this->config->device, $res));
        }
        return true;
    }
}
