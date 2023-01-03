<?php

$price = getCurrentHourEstimate();
$returnToSchedule = ($price <= 12);
$cacheFile = '/tmp/oj-thermostat.json';

$cacheData = json_decode(file_get_contents($cacheFile), true);
if ($cacheData !== false && $cacheData['returnToSchedule'] == $returnToSchedule) {
    // Same state as before, just exit. No adjustment needed
    //echo "Same state, just exiting";
    exit;
}

$loginResponse = postCurl('https://mythermostat.info/api/authenticate/user', '{"Email": "'.getenv('LOGIN').'", "Password": "'.getenv('PASSWORD').'", "Application": 0}');
$loginData = json_decode($loginResponse, true);
if (empty($loginData) || $loginData['ErrorCode'] != 0) {
    die("Login error: " . $loginResponse . "\n");
}

//$thermostatResponse = json_decode(file_get_contents('https://mythermostat.info/api/thermostat?sessionid='.$loginData['SessionId'].'&serialnumber=' . getenv('SERIAL')));
//echo "$thermostatResponse\n";

// Over 11 cents - disable the thermostat
if ($returnToSchedule) {
    // Return to schedule:
//    echo "Returning to schedule";
    $data = postCurl("https://mythermostat.info/api/thermostat?sessionid={$loginData['SessionId']}&serialnumber=" . getenv('SERIAL'), '{"RegulationMode":1,"VacationEnabled":false}');
} else {
    // Turn down
//    echo "Disabling, too expensive";
    $data = postCurl("https://mythermostat.info/api/thermostat?sessionid={$loginData['SessionId']}&serialnumber=" . getenv('SERIAL'), '{"RegulationMode":3,"ManualTemperature":1800,"VacationEnabled":false}');
}

file_put_contents($cacheFile, json_encode(['returnToSchedule' => $returnToSchedule]));

function getCurrentHourEstimate() {
    $currentPrice = (float)file_get_contents('http://home.syrota.com/comed/data.php?currentPrice');
    $dayAheadPrice = json_decode(file_get_contents('http://home.syrota.com/comed/data.php?dayAheadToday'));
    if (empty($dayAheadPrice[(int)date('H')])) {
        var_dump($dayAheadPrice);
        die("Can't determine this hour's price");
    }
    $dayAheadNow = $dayAheadPrice[(int)date('H')];
    $estimateNow = max($dayAheadNow, $currentPrice);
//    echo "Current: $currentPrice\n";
//    echo "Estimate: $dayAheadNow\n";
//    echo "Result: $estimateNow\n";
    return $estimateNow;
}

function postCurl($url, $payload) {
    $ch = curl_init($url);
//    echo "$url <= $payload\n";
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $result = curl_exec($ch);
    curl_close($ch);
//    echo $result . "\n";
    return $result;
}
