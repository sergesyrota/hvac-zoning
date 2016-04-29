<?php

/**
 * This is a VERY early version of the controller.
 * it relies on hardcoded globals, and some infrastructure I already have, outside of this repository
 * It's here only for my own tracking, and should not be used :)
 */

define('DEBUG', false);

require_once '/var/www/home/dashboard/include/rs485.php';

$middleClosed = 0;
$middleOpen = 20;

$target = getTargetTemperature();
if (!empty($argv[1]) && $argv[1]=='getTarget') {
    echo $target;
    exit(0);
}

$current = json_decode(str_replace("'", '"', tryCmd('EnvMaster', 'getDht', 1)));
$current->t = $current->t/100;
debug(sprintf("Current temp is %.1f, target %.1f", $current->t, $target));
// If we're within +/- 0.3 degrees of target - do nothing
if (abs($current->t-$target) < 0.3) {
    debug("Doing nothing");
    exit(0);
}
// Need to close the vent, as we've reached the temperature
if ($current->t > $target) {
    debug("Closing master vent");
    tryCmd('MasterVent1', 'setDegrees:0');
    debug("Opening middle vents");
    tryCmd('MiddleVent1', 'setDegrees:'.$middleOpen);
    tryCmd('MiddleVent2', 'setDegrees:'.$middleOpen);
}
// Need to open the vent, as we're below the target
if ($current->t < $target) {
    debug("Opening the vent");
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

// This is hardcoded schedule; Target temp in C
function getTargetTemperature()
{
    $hour = (int)date('H');
    switch (date('N')) {
        case 1: // Mon
        case 2: // Tue
        case 3: // Wed
        case 4: // Thu
        case 5: // Fri
            // Same schedule for all weekdays
            // 22-5: 21, 5-8: 22.5, 8-16: 18, 16-22: 22.5
            if ($hour<5 || $hour>=22) {
                return 21;
            } elseif($hour<8 || $hour>=16) {
                return 23.5;
            } else {
                return 18;
            }
            break;
        case 6: // Sat
        case 7: // Sun
            // Same schedule for both days of weekend
            if ($hour<7 || $hour>=22) {
                return 21;
            } else {
                return 22.5;
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
