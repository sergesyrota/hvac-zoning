<?php

namespace Thermostat\Connector;

class Google {
    // Connection token for API
    private $clientId;
    private $clientSecret;
    private $refreshToken;
    // Token and expiration
    private $authToken;
    private $tokenExpireTimestamp;
    // Cache file to use to dump all thermostats
    private $cacheFile;
    // Last update of the data locally
    private $updateTime;
    // Cached full response
    private $response;

    /**
     * Communication with Google Device Access API
     * https://developers.google.com/nest/device-access/authorize
     * Go to: https://nestservices.google.com/partnerconnections/___PROJECT_ID___/auth?redirect_uri=https://www.google.com&access_type=offline&prompt=consent&client_id=___CLIENT_ID___&response_type=code&scope=https://www.googleapis.com/auth/sdm.service
     * Then copy "code" parameter from the redirect URL. Use that to obtain bearer and refresh tokens by running:
     *      curl -L -X POST 'https://www.googleapis.com/oauth2/v4/token?client_id=___CLIENT_ID___&client_secret=___SECRET___&code=___CODE___&grant_type=authorization_code&redirect_uri=https://www.google.com'
     *
     * @param string $clientId GCP client ID that has access to devices
     * @param string $secret GCP secret for above client ID
     * @param string $code Google access code that was grated permissions to thermostat for the above client token
     * @param string $thermostatId Google thermostat identifier, as appears in the API (devices.name)
     * @param string $cacheFile Path to a file that can be used to store API response in full, allowing us to limit number of requests
     */
    public function __construct($clientId, $secret, $refreshToken, $thermostatId, $cacheFile = null) {
        if (empty($clientId) || empty($secret) || empty($refreshToken)) {
            throw new \Exception("Google client ID, secret, and refreshToken are all required.");
        }
        if (empty($thermostatId)) {
            throw new \Exception("Thermostat ID (as appears in devices.name) is required");
        }
        $this->clientId=$clientId;
        $this->clientSecret=$secret;
        $this->refreshToken=$refreshToken;
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
        $url = 'https://developer-api.nest.com/devices/thermostats/' . $tstatId;
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
            $uri = "https://www.googleapis.com/oauth2/v4/token?client_id={$this->clientId}&client_secret={$this->clientSecret}&refresh_token={$this->refreshToken}&grant_type=refresh_token";
            curl_setopt($ch, CURLOPT_URL, $uri);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Length: 0']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            //curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $res = curl_exec($ch);
            $data = json_decode($res, true);
            if (empty($data)) {
                throw new Exception("Empty response for Google refresh token.");
            }
            if (!empty($data['error'])) {
                throw new Exception("OAuth error: " . $data['error'] . ". Description: " . $data['error_description']);
            }
            $this->authToken = $data['access_token'];
            $this->tokenExpireTimestamp = time() + $data['expires_in'] - 10;
        }
        return $this->authToken;
    }
}

// $g = new Google(
//     'Client ID here',
//     'secret here',
//     'refresh token here',
//     'test');
// //$g->getData();
// var_dump($g->getAuthToken());
// echo "\n";
// var_dump($g->getAuthToken());
// echo "\n";
