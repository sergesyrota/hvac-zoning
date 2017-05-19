<?php

/**
 * This is a VERY early version of the controller.
 * it relies on hardcoded globals, and some infrastructure I already have, outside of this repository
 * It's here only for my own tracking, and should not be used :)
 */

define('DEBUG', false);
define('ACTION', true);

require_once '/var/www/home/dashboard/include/rs485.php';

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
$thermostat = json_decode(`curl http://192.168.8.90/tstat/ 2>/dev/null`);
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
                if (ACTION) {
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
    if ($mode == 'cool') {
        return ftoc(73);
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
    // Day off
    if (time() < strtotime('2017-04-08 00:00:00')) {
        return ftoc(62);
    } elseif (time() < strtotime('2017-04-08 15:00:00')) {
        return ftoc(76);
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
    $hour = (int)date('H');
    switch (date('N')) {
        case 1: // Mon
        case 2: // Tue
        case 3: // Wed
        case 4: // Thu
        case 5: // Fri
            // Same schedule for all weekdays
            // 22-5: 23, 5-8: 24.5, 8-16: 28, 16-22: 24.5
            if ($hour<5 || $hour>=22) {
                return 23;
            } elseif($hour<8 || $hour>=16) {
                return 24.5;
            } else {
                return 28;
            }
            break;
        case 6: // Sat
        case 7: // Sun
            // Same schedule for both days of weekend
            if ($hour<7 || $hour>=22) {
                return 23;
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
