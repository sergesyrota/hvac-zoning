<?php
// Very basic thermostat until normal one is integrated
// Response needs to have "mode" and "call" output as json

date_default_timezone_set(getenv('TIMEZONE'));
$tolerance = (float)getenv(TEMP_TOLERANCE); // Within these degrees, we're doing nothing

// include function to get current temperature getCurrentTemp
require_once(__DIR__ . getenv('GET_CURRENT_TEMP_FUNC_FILE'));

$schedule = json_decode(file_get_contents(__DIR__ . getenv('SCHEDULE_FILE')));

// Need to find timestamp of first previous point in whole of schedule
$time = strtotime('+1 day');
$closestTime = 0;
$temp = [];
foreach ($schedule as $entry) {
    foreach ($entry->day as $dayOfWeek) {
        foreach ($entry->temp as $target) {
            $timestamp = strtotime("last {$dayOfWeek} {$target->time}", $time);
            if ($timestamp > $closestTime && $timestamp < time()) {
                $temp = $target;
                $closestTime = $timestamp;
            }
        }
    }
}

// All units are in F
$current = getCurrentTemp();
$response = getResponseTemplate($stateFile);
$response['current'] = $current;
$response['target']['heat'] = $temp->heat;
$response['target']['cool'] = $temp->cool;

$deltaHeat = abs($temp->heat - $current);
$deltaCool = abs($temp->cool - $current);
// Hysteresis
if ($deltaHeat < $tolerance || $deltaCool < $tolerance) {
    // Leave call to action the same if we're within tolerance
    exitWithData($response);
}
// Cooling mode
if ($current > ($temp->cool+0.5)) {
    $response['call'] = 'cool';
}
// Heating mode
if ($current < ($temp->heat-0.5)) {
    $response['call'] = 'heat';
}
// Clear out previously set call, if we've reached the temp
if (
    $response['call'] == 'heat' && $current > ($temp->heat + getenv('TEMP_TOLERANCE'))
    || $response['call'] == 'cool' && $current < ($temp->cool - getenv('TEMP_TOLERANCE'))
) {
    $response['call'] = 'off';
}
exitWithData($response);

function exitWithData($data) {
    file_put_contents(getenv('STATE_FILE'), json_encode($data));
    echo json_encode($data);
    exit(0);
}

function getResponseTemplate() {
    $stateFile = getenv('STATE_FILE');
    if (file_exists($stateFile) && is_readable($stateFile)) {
        $data = json_decode(file_get_contents($stateFile), true);
        if (!empty($data) && !empty($data['mode']) && !empty($data['call'])) {
            return $data;
        }
    }
    // If none is cached, use this default
    return [
        'mode' => 'auto',
        'call' => 'off',
        'target' => [
            'cool' => 999,
            'heat' => -999
        ],
        'current' => null
    ];
}

function FtoC($t) {
    return ($t - 32) / 1.8;
}
