<?php

namespace Thermostat;

class Cli implements iThermostat {
    // Connection configuration
    private $config;
    // Cached response
    private $response;
    // Time since response was obtained
    private $updateTime;

    public function __construct($config) {
        $this->config = $config;
        $this->validateConfig();
    }

    private function validateConfig() {
        if (empty($this->config->command)) {
            throw new \Exception('CLI thermostat needs command to be specified.');
        }
    }

    public function getMode() {
        $data = $this->getData();
        if (empty($data->mode)) {
            throw new Exception("Unknown mode for CLI thermostat {$this->config->command}");
        }
        switch ($data->mode) {
            case 'auto':
                return iThermostat::MODE_AUTO;
            case 'heat':
                return iThermostat::MODE_HEAT;
            case 'cool':
                return iThermostat::MODE_COOL;
            case 'off':
                return iThermostat::MODE_OFF;
            default:
                throw new \Exception("Unknown thermostat mode in CLI adapter: {$data->mode}");
        }
    }

    // Gets thermostat's call:
    // - heat
    // - cool
    // - null
    public function getCall() {
        $data = $this->getData();
        if (empty($data->call)) {
            throw new Exception("Empty call for CLI thermostat {$this->config->command}");
        }
        switch ($data->call) {
            case 'auto':
                return iThermostat::MODE_AUTO;
            case 'heat':
                return iThermostat::MODE_HEAT;
            case 'cool':
                return iThermostat::MODE_COOL;
            case 'off':
                return iThermostat::MODE_OFF;
            default:
                throw new \Exception("Unknown thermostat call in CLI adapter: {$data->call}");
        }
    }

    // Temperature difference between current and target
    public function deltaT($unit='F') {
        $this->getData();
        throw new \Exception("Not implemented");
    }

    // Gets currrent data, if older than TTL
    private function getData($ttl=30) {
        if (time() - $this->updateTime <= $ttl) {
            return $this->response;
        }
        $data = json_decode(`{$this->config->command}`);
        if ($data === false) {
            throw new \Exception("Error getting data from thermostat " . __CLASS__ . " `{$this->config->command}`");
        }
        $this->updateTime = time();
        $this->response = $data;
        return $data;
    }

    // Not supported
    public function setLogger($logger) {

    }
}
