<?php

/*
if (empty($argv[1]) || !file_exists($argv[1])) {
    echo "USAGE: cron.php <thermostats.json>\n";
    die();
}*/

require_once __DIR__ . '/bootstrap.php';

// CRON setup and re-run protection
$pidFile = App::getRequiredEnv('APP_PID_FILE');
if (!is_writeable(dirname($pidFile))) {
    throw new Exception("PID file should be in writeable folder");
}
// Exit if the process is running.
if (isProcessRunning($pidFile)) {
    exit(0);
}
file_put_contents($pidFile, posix_getpid());
function removePidFile() {
    unlink(App::getRequiredEnv('APP_PID_FILE'));
}
register_shutdown_function('removePidFile');
// END CRON setup

$app = new App(App::getRequiredEnv('TSTATS_JSON'));
$app->run();

function isProcessRunning($pidFile) {
    if (!file_exists($pidFile) || !is_file($pidFile)) return false;
    $pid = file_get_contents($pidFile);
    // Check if process is dead
    if (time() - filemtime($pidFile) > 300) {
        posix_kill($pid, SIGKILL);
        return false;
    }
    return posix_kill($pid, 0);
}
