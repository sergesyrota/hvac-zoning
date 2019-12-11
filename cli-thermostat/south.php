<?php

function getCurrentTemp($unit='F') {
    $data = json_decode(file_get_contents('http://guest-thermostat.iot.syrota.com:5000/'));
    if ($unit == $data->temperature_unit) {
        return $data->temperature;
    }
    if ($unit == 'F') {
        return CtoF($data->temperature);
    } else {
        return FtoC($data->temperature);
    }
}
