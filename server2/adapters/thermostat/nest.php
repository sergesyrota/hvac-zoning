<?php

namespace Thermostat;

class Nest implements iThermostat {
    // Connection configuration
    private $config;
    // Bearer bearer
    private $bearerToken;
    // Thermostat ID
    private $thermostatId;
    // Cached response
    private $response;
    // Time since response was obtained
    private $updateTime;

    public function __construct($config) {
        $this->config = $config;
        $this->validateConfig();
        $this->bearerToken = getenv($this->config->bearer_token);
        $this->thermostatId = getenv($this->config->device_id);
    }

    private function validateConfig() {
        // Config should be identification of environment variables for API connection info
        if (empty($this->config->bearer_token) || empty(getenv($this->config->bearer_token))) {
            throw new \Exception('Nest thermostat needs bearer token to be specified and present in environment.');
        }
        if (empty($this->config->device_id) || empty(getenv($this->config->device_id))) {
            throw new \Exception('Nest thermostat needs device ID to be specified and present in environment.');
        }
    }

    public function getMode() {
        $data = $this->getData();
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
        $data = $this->getData();
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
    public function deltaT($unit='F') {
        $this->getData();
        throw new \Exception("Not implemented");
    }

    // Gets currrent data, if older than TTL
    private function getData($ttl=60) {
        if (time() - $this->updateTime <= $ttl) {
            return $this->response;
        }
        $url = 'https://developer-api.nest.com/devices/thermostats/' . $this->thermostatId;
        $headers = [
            'Authorization: Bearer c.' . $this->bearerToken,
            'Content-type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $res = curl_exec($ch);
        $data = json_decode($res);
        if ($data === false) {
            throw new \Exception("Error getting data from thermostat " . __CLASS__ . "; Invalid response.");
        }
        if (!empty($data->error)) {
            throw new \Exception("NEST API error: " . $data->message);
        }
        $this->updateTime = time();
        $this->response = $data;
        return $this->response;
    }
}
