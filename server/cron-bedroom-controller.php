<?php

/**
 * This is a VERY early version of the controller.
 * it relies on hardcoded globals, and some infrastructure I already have, outside of this repository
 * It's here only for my own tracking, and should not be used :)
 */

define('DEBUG', false);
define('ACTION', true);

require_once '/var/www/home/dashboard/include/rs485.php';
require_once '/home/sergey/temps/hvac-zoning/server/middleTstat.php';

// Degrees from most open to most closed.
$degrees = [
    'open' => 90,
    'cracked' => 30,
    'closed' => 0
];

$roomVents = [
    'master' => ['MasterVent1'],
    'middle' => ['MiddleVent1', 'MiddleVent2'],
    'guest' => ['GuestVent'],
];

// AC or Heat
$mode = null;
// Get thermostat mode
$thermostat = getMiddleTstat();
if ($thermostat->tmode == 2) {
    $mode = 'cool';
} else {
    $mode = 'heat';
}

$target = getTargetTemperature($mode);
if (!empty($argv[1]) && $argv[1]=='getTarget') {
    echo $target;
    exit(0);
}
$targetGuest = getTargetGuestTemperature($mode);
if (!empty($argv[1]) && $argv[1]=='getTargetGuest') {
    echo $targetGuest;
    exit(0);
}

// Guest
$currentGuest = json_decode(file_get_contents('http://guest-thermostat.iot.syrota.com:5000/'));
debug('Guest thermostat data: ' . var_export($currentGuest, true));
// Master
$current = json_decode(tryCmd('EnvMaster', 'getDht', 1));
$current->t = $current->t/100;
debug(sprintf("Current Master temp is %.1f, target %.1f", $current->t, $target));
debug(sprintf("Current Guest temp is %.1f, target %.1f", $currentGuest->temperature, $targetGuest));
// If we're within +/- 0.3 degrees of target - do nothing
if (abs($current->t-$target) < 0.3 && abs($currentGuest->temperature-$targetGuest) < 0.3) {
    debug("Doing nothing");
    exit(0);
}
$targetVentState = [
    'master' => 'open',
    'middle' => 'open',
    'guest' => 'open'
];
if (($mode == 'heat' && $current->t > $target) || ($mode == 'cool' && $current->t < $target)) {
    $targetVentState['master'] = 'closed';
} 
if (($mode == 'heat' && $currentGuest->temperature > $targetGuest) || ($mode == 'cool' && $currentGuest->temperature < $targetGuest)) {
    $targetVentState['guest'] = 'closed';
}
if ($targetVentState['master'] == 'open' || $targetVentState['guest'] == 'open') {
    $targetVentState['middle'] = 'closed';
}
if ($targetVentState['master'] == 'closed' && $targetVentState['guest'] == 'closed') {
    $targetVentState['middle'] = 'open';
    $targetVentState['master'] = 'cracked';
    $targetVentState['guest'] = 'cracked';
}
debug('Target vent state: ' . var_export($targetVentState, true));

    // go from most-open to most closed
foreach ($degrees as $positionName=>$deg) {
    foreach ($roomVents as $roomName=>$vents) {
        if ($targetVentState[$roomName] == $positionName) {
            foreach ($vents as $vent) {
                debug("Setting $roomName $vent at $deg");
                if (ACTION && $vent != 'GuestVent') {
                    tryCmd($vent, 'setDegrees:' . $deg);
                }
                sleep(3);
            }
        }
    }
}

function debug($msg)
{
    if (DEBUG) {
        echo $msg."\n";
    }
}

function ftoc($f) {
	return ($f - 32) / 1.8;
}

function getTargetGuestTemperature($mode) {
    $hour = (int)date('H');
    if ($mode == 'cool') {
        switch (date('N')) {
            case 1: // Mon
            case 2: // Tue
            case 3: // Wed
            case 4: // Thu
            case 5: // Fri
                if ($hour>=13 && $hour<16) {
                    return ftoc(74);
                }
                // Fall through to default, as only on weekdays we override nap time for Andrew
            default:
                // Same schedule for all weekdays
                // 22-5: 21, 5-8: 22, 8-16: 20, 16-22: 22
                if ($hour<7 || $hour>=20) {
                    return ftoc(72);
                } else {
                    return ftoc(78);
                }
                return;
        }
    } else {
        return ftoc(71);
    }
}

// This is hardcoded schedule; Target temp in C
function getTargetTemperature($mode)
{
    if ($mode == 'cool') {
        return getTargetCoolTemperature();
    } else {
        return getTargetHeatTemperature();
    }
}

function getTargetHeatTemperature()
{
    $hour = (int)date('H');
    // Vacation
    if (time() < strtotime('2017-06-23 16:00:00')) {
        return ftoc(62);
    }
    switch (date('N')) {
        case 1: // Mon
        case 2: // Tue
        case 3: // Wed
        case 4: // Thu
        case 5: // Fri
            // Same schedule for all weekdays
            // 22-5: 21, 5-8: 22, 8-16: 20, 16-22: 22
            if ($hour<5 || $hour>=22) {
                return ftoc(68);
            } elseif($hour<8 || $hour>=16) {
                return ftoc(72);
            } else {
                return ftoc(68);
            }
            break;
        case 6: // Sat
        case 7: // Sun
            // Same schedule for both days of weekend
            if ($hour<7 || $hour>=22) {
                return ftoc(68);
            } else {
                return ftoc(72);
            }
            break;
    }
}

// This is hardcoded schedule; Target temp in C
function getTargetCoolTemperature()
{
    // guest bedroom work
    if (time() < strtotime('2017-07-25 18:00:00')) {
        return ftoc(71);
    }
    $hour = (int)date('H');
    switch (date('N')) {
        case 1: // Mon
        case 2: // Tue
        case 3: // Wed
        case 4: // Thu
        case 5: // Fri
            // Same schedule for all weekdays
            // 22-5: 23, 5-8: 24.5, 8-16: 28, 16-22: 24.5
            if ('2017-07-05' == date('Y-m-d')) {
                return 22;
            }
            if ($hour<5 || $hour>=21) {
                return 22;
            } elseif($hour<8 || $hour>=20) {
                return 24.5;
            } else {
                return 28;
            }
            break;
        case 6: // Sat
        case 7: // Sun
            // Same schedule for both days of weekend
            if ($hour<7 || $hour>=22) {
                return 22;
            } else {
                return 24.5;
            }
            break;
    }
}

// Makes a few attempts to get results from RS485;
function tryCmd($device, $command, $attempts=3) {
    $rs = new rs485();
    $lastException = new Exception('Unknown error?');
    for ($i=0; $i<$attempts; $i++) {
        try {
            $out = $rs->command($device, $command);
            return $out;
        } catch(Exception $e) {
            $lastException = $e;
        }
    }
    throw $lastException;
}

// Make several attempts to get tstat data, as sometimes it's not working.
/*function getMiddleTstat() {
    for ($i=0; $i<5; $i++) {
        $data = json_decode(`curl -m 60 http://192.168.8.90/tstat/ 2>/dev/null`);
        if ($data !== null && !empty($data->tmode)) {
            if (($data->tmode == 2 && !empty($data->t_cool)) || !empty($data->t_heat)) {
                return $data;
            }
        }
        sleep(4);
    }
    throw new Exception("Cannot get thermostat data using curl.");
}
*/