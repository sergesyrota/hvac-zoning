<?php

define('PIDFILE', '/tmp/middleTstat.pid');
define('DATAFILE', '/tmp/middleTstat.json');

if (isset($argv[1]) && $argv[1] == 'cron') {
    if (processRunning()) {
        exit(0);
    }
    file_put_contents(PIDFILE, posix_getpid());
    register_shutdown_function('removePidFile');

    // In a loop, always update thermostat data
    while (true) {
        $data = getTstatData();
        file_put_contents(DATAFILE,json_encode($data));
        sleep(30);
    }
}

function removePidFile() {
    unlink(PIDFILE);
}

function processRunning() {
    if (!file_exists(PIDFILE) || !is_file(PIDFILE)) return false;
    $pid = file_get_contents(PIDFILE);
    return posix_kill($pid, 0);
}

// Make several attempts to get tstat data, as sometimes it's not working.
function getMiddleTstat() {
    if (!file_exists(DATAFILE)) {
        throw new Exception('Middle tstat data file does not exist');
    }
    $age = time() - filemtime(DATAFILE);
    if ($age > 600) {
        throw new Exception('Middle tstat data file is more than 10 minutes old');
    }
    $data = json_decode(file_get_contents(DATAFILE));
    if ($data === null) {
        throw new Exception('Middle tstat file contents is empty.');
    }
    return $data;
}

function getTstatData() {
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


