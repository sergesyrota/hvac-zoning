<?php

$price = getCurrentHourEstimate();
// Supply price only
$returnToSchedule = ($price <= 4);
$cacheFile = '/tmp/oj-thermostat.json';

$cacheData = json_decode(file_get_contents($cacheFile), true);
if ($cacheData !== false && $cacheData['returnToSchedule'] == $returnToSchedule) {
    // Same state as before, just exit. No adjustment needed
    //echo "Same state, just exiting";
//    exit;
}
/*
$loginResponse = postCurl('https://mythermostat.info/api/authenticate/user', '{"Email": "'.getenv('LOGIN').'", "Password": "'.getenv('PASSWORD').'", "Application": 0}');
$loginData = json_decode($loginResponse, true);
if (empty($loginData) || $loginData['ErrorCode'] != 0) {
    die("Login error: " . $loginResponse . "\n");
}
*/
$loginData['SessionId'] = getenv('SESSION_ID');

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
$json = json_decode($data, true);

if ($json === false || $json['Success'] !== true) {
    if (rand(0,10)<=1) {
        echo "Looks like there was an error in the API: " . $data;
    }
}

file_put_contents($cacheFile, json_encode(['returnToSchedule' => $returnToSchedule]));

function getCurrentHourEstimate() {
    // This already takes care of the forecast
    $currentPrice = (float)file_get_contents('http://home.syrota.com/comed/data.php?currentSupplyPrice');
    return $currentPrice;
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
