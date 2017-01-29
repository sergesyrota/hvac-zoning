<?php

/**
 * This is a VERY early version of the controller.
 * it relies on hardcoded globals, and some infrastructure I already have, outside of this repository
 * It's here only for my own tracking, and should not be used :)
 */

define('DEBUG', false);

require_once '/var/www/home/dashboard/include/rs485.php';

$middleClosed = 0;
$middleOpen = 50;

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

$current = json_decode(tryCmd('EnvMaster', 'getDht', 1));
$current->t = $current->t/100;
debug(sprintf("Current temp is %.1f, target %.1f", $current->t, $target));
// If we're within +/- 0.3 degrees of target - do nothing
if (abs($current->t-$target) < 0.3) {
    debug("Doing nothing");
    exit(0);
}
// Need to close the vent, as we've reached the temperature
// In heat mode, it needs to be over target, in cool mode, it needs to be under target
if (($mode == 'heat' && $current->t > $target)
    || ($mode == 'cool' && $current->t < $target)) {
    debug("Closing master vent");
    tryCmd('MasterVent1', 'setDegrees:0');
    debug("Opening middle vents");
    tryCmd('MiddleVent1', 'setDegrees:'.$middleOpen);
    tryCmd('MiddleVent2', 'setDegrees:'.$middleOpen);
}
// Need to open the vent, as we're below the target
if (($mode == 'heat' && $current->t < $target)
    || ($mode == 'cool' && $current->t > $target)) {
    debug("Opening master vent");
    tryCmd('MasterVent1', 'setDegrees:90');
    debug("Closing middle vents");
    tryCmd('MiddleVent1', 'setDegrees:'.$middleClosed);
    tryCmd('MiddleVent2', 'setDegrees:'.$middleClosed);
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
    if ('2017-01-02' == date('Y-m-d')) {
        return ftoc(72);
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
