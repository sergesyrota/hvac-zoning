<?php

require_once __DIR__ . '/vendor/autoload.php';

function getCurrentTemp($unit='F') {
    $gm = new \SyrotaAutomation\Gearman(getenv('RS485V1_TASK'), getenv('RS485V1_HOST'));
    $data = json_decode($gm->command('EnvMaster', 'getDht'));
    if (empty($data)) {
        throw new \Exception("Can't get data for Master environment sensor.");
    }
    if ($data->age > 60) {
        throw new \Exception("Master environment sensor has outdated temperature reading.");
    }
    $temp = CtoF($data->t/100);
    if ($unit == 'C') {
        return FtoC($temp);
    }
    return $temp;
}
