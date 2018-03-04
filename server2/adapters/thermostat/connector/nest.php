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

    // Gets currrent data, if older than TTL
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

    // Gets all available thermostats' data from API
    private function getDatafromApi() {
        $url = 'https://developer-api.nest.com/devices/thermostats/';
        $headers = [
            'Authorization: Bearer c.' . $this->token,
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
        return $data;
    }
}
