<?php

namespace Thermostat\Connector;

class Nest {
    // Connection token for API
    private $token;
    // Cache file to use to dump all thermostats
    private $cacheFile;
    // Last update of the data locally
    private $updateTime;
    // Cached full response
    private $response;

    /**
     * Communication with NEST API
     *
     * @param string $token Bearer token to use for authorization. Must be authorized for access to needed thermostats
     * @param string $token NEST thermostat identifier, as appears in the API
     * @param string $cacheFile Path to a file that can be used to store API response in full, allowing us to limit number of requests
     */
    public function __construct($token, $thermostatId, $cacheFile = null) {
        if (empty($token)) {
            throw new \Exception("NEST token cannot be empty.");
        }
        if (empty($thermostatId)) {
            throw new \Exception("NEST Thermostat ID is required");
        }
        $this->token=$token;
        $this->cacheFile = $cacheFile;
        $this->thermostatId = $thermostatId;
    }

    /**
     * Gets up to date data on the thermostat
     *
     * @param int $ttl Number of seconds the data will expire after initial acquisition. Used for both file cache refresh, and local variable refresh.
     */
    public function getData($ttl=45) {
        // Use cache file if we have it configured
        if (!empty($this->cacheFile)) {
            if (!file_exists($this->cacheFile)
                || (time() - filemtime($this->cacheFile)) > $ttl
                || empty(json_decode(file_get_contents($this->cacheFile))))
            {
                $allData = $this->getDatafromApi();
                file_put_contents($this->cacheFile, json_encode($allData));
                // Need to make sure next time we query mtime, it's not retreived form cache
                clearstatcache();
                return $allData->{$this->thermostatId};
            } else {
                $allData = json_decode(file_get_contents($this->cacheFile));
                return $allData->{$this->thermostatId};
            }
        }
        // If cache file is not specified, just cache data in class variable
        if (time() - $this->updateTime <= $ttl) {
            return $this->response;
        }
        $allData = $this->getDatafromApi();
        $this->updateTime = time();
        $this->response = $allData;
        return $this->response->{$this->thermostatId};
    }

    /**
     * Sets current target temperature. Only works in heat or cool mode, but not in auto modes.
     *
     * @param int $targetF Target temperature in Fahrenheit (no decimals)
     */
    public function setTarget($targetF) {
        $data = $this->getData();
        if (!in_array($data->hvac_mode, ['cool', 'heat'])) {
            throw new \Exception("Target temperature adjustment is only supported in cool or heat modes, not " . $data->hvac_mode);
        }
        $res = $this->apiCall($this->thermostatId, 'PUT', json_encode(['target_temperature_f' => $targetF]));
        $data = json_decode($res);
        if ($data === false) {
            throw new \Exception("Error setting thermostat temperature " . __CLASS__ . "; Invalid response.");
        }
        if (!empty($data->error)) {
            throw new \Exception("NEST API error: " . $data->message);
        }
        return true;
    }

    /**
     * Extract data from live NEST API
     */
    private function getDatafromApi() {
        $res = $this->apiCall();
        $data = json_decode($res);
        if ($data === false) {
            throw new \Exception("Error getting data from thermostat " . __CLASS__ . "; Invalid response.");
        }
        if (!empty($data->error)) {
            throw new \Exception("NEST API error: " . $data->message);
        }
        return $data;
    }

    /**
     * Makes an API call to NEST
     *
     * @param string $tstatId If not provided, all authorized thermostats are returned. If provided, restricted to the ID only
     * @param string $method HTTP method of the API request
     * @param string $reqBody If we're making a POST/PATCH/etc data for the request to pass on
     */
    private function apiCall($tstatId = '', $method = 'GET', $reqBody = '') {
        $url = 'https://developer-api.nest.com/devices/thermostats/' . $tstatId;
        $headers = [
            'Authorization: Bearer c.' . $this->token,
            'Content-type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if (!empty($reqBody)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $reqBody);
        }
        $res = curl_exec($ch);
        return $res;
    }
}
