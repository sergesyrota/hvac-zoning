<?php

namespace Thermostat\Connector;

class Homebridge {



    // Connection token for API
    private $baseURL;
    private $username;
    private $password;
    // Token and expiration
    private $authToken;
    private $tokenExpireTimestamp;
    // UUID of "Accessory" representing the thermostat instance
    private $accessoryId;
    // Cache file to use to dump all thermostats
    private $cacheFile;
    // Last update of the data locally
    private $updateTime;
    // Cached full response
    private $response;

    /**
     * Communication with Homebridge API
     * Homebdige.local/swagger
     *
     * @param string $baseURL Location of Homebridge
     * @param string $username
     * @param string $password
     * @param string $accessoryId UUID of the thermostat in Homekit
     * @param string $cacheFile Path to a file that can be used to store API response in full, allowing us to limit number of requests
     */
    public function __construct($baseURL, $username, $password, $accessoryId, $cacheFile = null) {
        if (empty($baseURL) || empty($username) || empty($password)) {
            throw new \Exception("Base URL, username, and password are all required.");
        }
        if (empty($accessoryId)) {
            throw new \Exception("Accessory ID (as appears in /api/accessories) is required");
        }
        $this->baseURL=$baseURL;
        $this->username=$username;
        $this->password=$password;
        $this->cacheFile = $cacheFile;
        $this->accessoryId = $accessoryId;
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
                || (time() - filemtime($this->cacheFile)) >= $ttl
                || empty(json_decode(file_get_contents($this->cacheFile))))
            {
                $allData = $this->getDatafromApi();
                if (empty($allData)) {
                    throw new \Exception("Cannot get data from NEST API.");
                }
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
        // Refresh data, as we've just messed with stuff, and need to make sure we get full current state
        $this->getData(0);
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
     * Makes an API call to Google
     *
     * @param string $tstatId If not provided, all authorized thermostats are returned. If provided, restricted to the ID only
     * @param string $method HTTP method of the API request
     * @param string $reqBody If we're making a POST/PATCH/etc data for the request to pass on
     */
    private function apiCall($tstatId = '', $method = 'GET', $reqBody = '') {
        $url = $this->baseURL . $tstatId;
        $headers = [
            'Authorization: Bearer ' . $this->getAuthToken,
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

    public function getAuthToken() {
        if (empty($this->authToken) || $this->tokenExpireTimestamp <= time()) {
            $ch = curl_init();
            $uri = $this->baseURL . '/api/auth/login';
            curl_setopt($ch, CURLOPT_URL, $uri);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
              'username' => $this->username,
              'password' => $this->password
            ]));
            $headers = array();
            $headers[] = 'Accept: */*';
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $res = curl_exec($ch);
            $data = json_decode($res, true);
            if (empty($data)) {
                throw new \Exception("Empty response for Homebridge refresh token.");
            }
            if (empty($data['access_token'])) {
                throw new \Exception("OAuth error: " . $res);
            }
            $this->authToken = $data['access_token'];
            $this->tokenExpireTimestamp = time() + $data['expires_in'] - 10;
        }
        return $this->authToken;
    }
}

$g = new Homebridge(
    'http://homebridge.syrota.com',
    '',
    '',
    'accessory ID');
//$g->getData();
var_dump($g->getAuthToken());
echo "\n";
var_dump($g->getAuthToken());
echo "\n";
