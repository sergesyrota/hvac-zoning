<?php

namespace App\Service;

class Thermostat {
    // Redis database service (snc_redis)
    private $_db;

    public function __construct($db) {
        $this->_db = $db;
    }

    public function getList() {
        //$db = $this->container-get('snc_redis.default');
        $tstats = json_decode($this->_db->get('tstats'));
        return $tstats;
    }
}
