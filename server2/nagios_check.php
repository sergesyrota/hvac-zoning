#!/usr/bin/php
<?php

$envFile = __DIR__ . '/zones/.env.bedrooms';
$bedEnv = getCustomEnv($envFile);
$state = json_decode(file_get_contents($bedEnv['STATE_FILE']), true);

$exitCode = 0; // OK

$timeSinceUpdate =  time() - $state['last_update'];
$message = "Last update: {$timeSinceUpdate}s; ";
if ($timeSinceUpdate > 1800) {
    $exitState = 2;
}

$nestFile = $bedEnv['NEST_CACHE_FILE_PREFIX'] . md5($bedEnv['NEST_TOKEN']);
$nest = json_decode(file_get_contents($nestFile), true);

foreach ($nest as $tstat) {
    $connectionTimeDiff = time() - strtotime($tstat['last_connection']);
    $message .= "{$tstat['name']}: {$connectionTimeDiff}s; ";
    if ($connectionTimeDiff > 1800) {
        $exitState = 2;
    }
}

echo ($exitState == 0 ? 'OK ' : 'CRITICAL ') . $message;
exit($exitState);

function getCustomEnv($file) {
    $contents = file_get_contents($file);
    preg_match_all('%(?P<key>.*)=(?P<val>.*)%', $contents, $matches);
    $res = [];
    foreach ($matches['key'] as $id => $key) {
        $res[$key] = $matches['val'][$id];
    }
    return $res;
}