<?php

/**
 * This is a VERY early version of the controller.
 * it relies on hardcoded globals, and some infrastructure I already have, outside of this repository
 * It's here only for my own tracking, and should not be used :)
 */

define('DEBUG', false);

require_once '/var/www/home/dashboard/include/rs485.php';

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
    debug("Closing the vent");
    tryCmd('MasterVent1', 'setDegrees:0');
}
// Need to open the vent, as we're below the target
if ($current->t < $target) {
    debug("Opening the vent");
    tryCmd('MasterVent1', 'setDegrees:90');
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
                return 22.5;
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

/*
<?php

// Time,Intake Temp,Intake Pressure,Vacuum Temp,Vacuum Pressure,Supply Temp,Supply Pressure,Master Vent Position,Master Vent Temp,Master Vent Pressure,Master Bed Temp
$data = [date('Y-m-d H:i:s')];
$commands = [
'/var/www/home/py/runCommand.py BrAcSens getTempIntake',
'/var/www/home/py/runCommand.py BrAcSens getPresIntake',
'/var/www/home/py/runCommand.py BrAcSens getTempVacuum',
'/var/www/home/py/runCommand.py BrAcSens getPresVacuum',
'/var/www/home/py/runCommand.py BrAcSens getTempSupply',
'/var/www/home/py/runCommand.py BrAcSens getPresSupply',
'/var/www/home/py/runCommand.py MasterVent1 getPosition'
];

try {
    foreach ($commands as $cmd) {
        $data[] = tryCmd($cmd);
    }
    // bedroom damper temp
    $vent = tryCmd('/var/www/home/py/runCommand.py MasterVent1 getSensor');
    preg_match('%(\d+) C\*100; (\d+) Pa \((\d+) ms ago\); status: (\d)%', $vent, $matches);
    if ($matches[3] > 60000) {
        throw new Exception("Vent data too old: $vent");
    }
    $data[] = $matches[1]/100;
    $data[] = $matches[2];
    // Master bedroom temp
    $dht = json_decode(str_replace("'", '"', tryCmd('/var/www/home/py/runCommand.py EnvMaster getDht')));
    if ($dht->secAgo > 60) {
        throw new Exception("Master bedroom data too old: " . var_export($dht, true));
    }
    $data[] = $dht->t/100;
    fputcsv(STDOUT, $data);
} catch(Exception $e) {
    fwrite(STDERR, $e->getMessage());
}

*/