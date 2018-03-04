<?php

namespace Thermostat;

class Nest implements iThermostat {
    // Connector class for interfacing with API
    private $con;

    public function __construct(Connector\Nest $connector) {
        $this->con = $connector;
    }

    public function getMode() {
        $data = $this->con->getData();
        if (empty($data->hvac_mode)) {
            throw new \Exception("Unknown mode for NEST thermostat {$this->thermostatId}");
        }
        switch ($data->hvac_mode) {
            case 'heat-cool':
            case 'eco':
                return iThermostat::MODE_AUTO;
            case 'heat':
                return iThermostat::MODE_HEAT;
            case 'cool':
                return iThermostat::MODE_COOL;
            case 'off':
                return iThermostat::MODE_OFF;
            default:
                throw new \Exception("Unknown thermostat mode in NEST adapter: {$data->hvac_mode}");
        }
    }

    // Gets thermostat's call:
    // - heat
    // - cool
    // - null
    public function getCall() {
        $data = $this->con->getData();
        if (empty($data->hvac_state)) {
            throw new \Exception("Empty call for NEST thermostat {$this->thermostatId}");
        }
        switch ($data->hvac_state) {
            case 'heating':
                return iThermostat::MODE_HEAT;
            case 'cooling':
                return iThermostat::MODE_COOL;
            case 'off':
                return iThermostat::MODE_OFF;
            default:
                throw new \Exception("Unknown thermostat call in NEST adapter: {$data->hvac_state}");
        }
    }

    // Temperature difference between current and target
    // Positive, means we've overshot. Negative, need to work toward target.
    public function deltaT($unit='F') {
        $data = $this->con->getData();
        // Need to translate to heat or cool, depending where we're at
        switch ($data->hvac_mode) {
            case 'heat-cool':
                return $this->getAutoDelta($data, $data->target_temperature_low_f, $data->target_temperature_high_f);
            case 'eco':
                return $this->getAutoDelta($data, $data->eco_temperature_low_f, $data->eco_temperature_high_f);
            case 'cool':
                return $data->target_temperature_f - $data->ambient_temperature_f;
            case 'heat':
                return $data->ambient_temperature_f - $data->target_temperature_f;
            case 'off':
                return 0;
            default:
                throw new \Exception("Unknown thermostat mode in NEST adapter: {$data->hvac_mode}");
        }
        throw new \Exception("Unknown state in NEST thermostat: {$data->hvac_state}");
    }

    // When mode is auto, figure out which target is closer
    private function getAutoDelta($data, $heatTarget, $coolTarget) {
        $t = $data->ambient_temperature_f;
        if (abs($t-$heatTarget) < abs($t-$coolTarget)) {
            // Heat mode
            return $heatTarget - $t;
        } else {
            // Cool mode
            return $t - $coolTarget;
        }
        throw new Exception('Cannot figure out AutoDelta.');
    }
}
