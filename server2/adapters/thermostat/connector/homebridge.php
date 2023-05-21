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
     */
    public function __construct($baseURL, $username, $password, $accessoryId) {
        if (empty($baseURL) || empty($username) || empty($password)) {
            throw new \Exception("Base URL, username, and password are all required.");
        }
        if (empty($accessoryId)) {
            throw new \Exception("Accessory ID (as appears in /api/accessories) is required");
        }
        $this->baseURL=$baseURL;
        $this->username=$username;
        $this->password=$password;
        $this->accessoryId = $accessoryId;
    }

    /**
     * Gets up to date data on the thermostat
     *
     * @param int $ttl Number of seconds the data will expire after initial acquisition. Used for both file cache refresh, and local variable refresh.
     */
    public function getData($ttl=45) {
        // Cache data locallu not to have to make multiple API calls
        if (time() - $this->updateTime <= $ttl) {
            return $this->response;
        }
        $thermostatData = $this->getDatafromApi();
        $this->updateTime = time();
        $this->response = $thermostatData;
        foreach ($this->response['serviceCharacteristics'] as $key=>$characteristic) {
          if (isset($this->response['serviceCharacteristicsByType'][$characteristic['type']])) {
            throw new \Exception('Duplicate characteristic by type detected ('.$characteristic['type'].')');
          }
          $this->response['serviceCharacteristicsByType'][$characteristic['type']] = $characteristic;
        }
        return $this->response;
    }

    /**
     * Sets current target temperature. Only works in heat or cool mode, but not in auto modes.
     *
     * @param int $targetF Target temperature in Fahrenheit (no decimals)
     */
    public function setTarget($targetF) {
        $data = $this->getData();
        // https://developers.homebridge.io/#/characteristic/TargetHeatingCoolingState
        // 0 = off, 1 = heat, 2 = cool, 3 = auto
        if (!in_array($data['serviceCharacteristicsByType']['TargetHeatingCoolingState']['value'], [1, 2])) {
            throw new \Exception("Target temperature adjustment is only supported in cool (2) or heat (1) modes, not " . $data['serviceCharacteristicsByType']['TargetHeatingCoolingState']['value']);
        }
        $res = $this->apiCall('PUT', json_encode([
          'characteristicType' => 'TargetTemperature',
          'value' => strval($this->FtoC($targetF))
        ]));
        $data = json_decode($res, true);
        if ($data === false) {
            throw new \Exception("Error setting thermostat temperature " . __CLASS__ . "; Invalid response: " . $res);
        }
        if (!empty($data['error'])) {
            throw new \Exception("Homebridge API error: " . $res);
        }
        // Refresh data, as we've just messed with stuff, and need to make sure we get full current state
        $this->getData(0);
        return true;
    }

    private function FtoC($F) {
      return ($F - 32) * 5/9;
    }

    /**
     * Extract data from live NEST API
     */
    private function getDatafromApi() {
        $res = $this->apiCall();
        $data = json_decode($res, true);
        if ($data === false) {
            throw new \Exception("Error getting data from thermostat " . __CLASS__ . "; Invalid response.");
        }
        if (empty($data['uuid'])) {
            throw new \Exception("Homebridge API error: " . $res);
        }
        return $data;
    }

    /**
     * Makes an API call to Homebridge
     *
     * @param string $method HTTP method of the API request
     * @param string $reqBody If we're making a POST/PATCH/etc data for the request to pass on
     */
    private function apiCall($method = 'GET', $reqBody = '') {
        $url = $this->baseURL . "/api/accessories/" . $this->accessoryId;
        $headers = [
            'Authorization: Bearer ' . $this->getAuthToken(),
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
