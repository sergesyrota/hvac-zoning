<?php

namespace Vent;

class RS485v1 implements iVent {
    // Vent connection information
    private $config;
    // Automation protocol library
    private $gm;
    // Recording error reason from the last check
    private $errorReason;

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

    public function getHumanReadableName() {
        return $this->config->device;
    }

    public function errorPresent() {
        $res = $this->gm->command($this->config->device, 'errorPresent');
        if ($res == 'NO') {
            $this->errorReason = null;
            return false;
        }
        $this->errorReason = $res;
        return true;
    }

    public function errorReason() {
        return $this->errorReason;
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

    public function selfHeal() {
        if ($this->errorReason === null) {
            return;
        }
        // turn back 180 degrees or so, in cse we can't turn all 360, and that being the reason for sticking
        $res = $this->gm->command($this->config->device, 'step:-4000');
        sleep(6); // 10 RPM, 6 seconds for 1 full rotation
        $res = $this->gm->command($this->config->device, 'calibrate');
        sleep(6); // 10 RPM, 6 seconds for 1 full rotation
        if ($this->errorPresent()) {
            throw new \Exception(sprintf("Failed to self-heal vent %s; Error: %s", $this->config->device, $this->errorReason));
        }
    }
}
