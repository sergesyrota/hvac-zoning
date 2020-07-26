<?php

$envFile = __DIR__ . '/zones/.env.bedrooms';
$bedEnv = getCustomEnv($envFile);

if (!empty($_POST['reset'])) {
    $state_mod = json_decode(file_get_contents($bedEnv['STATE_FILE']), true);
    $state_mod['override_present'] = false;
    file_put_contents($bedEnv['STATE_FILE'], json_encode($state_mod, JSON_PRETTY_PRINT));
}

echo '<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="mobile-web-app-capable" content="yes">
</head>
<body style="font-size:130%;">
';

// Script state details
$state = json_decode(file_get_contents($bedEnv['STATE_FILE']), true);
echo "<b>Last update</b>: ".date('H:i:s', time() - $state['last_update'] + 6*3600)." ago.<br>\n";
echo "<b>Override</b>: " . ($state['override_present'] ? "set " . date('H:i:s', time() - strtotime($state['override_set_time']) + 6*3600) . " ago" : "not set") . "<br>\n";

// Nest data
$nestFile = $bedEnv['NEST_CACHE_FILE_PREFIX'] . md5($bedEnv['NEST_TOKEN']);
$nest = json_decode(file_get_contents($nestFile), true);

echo "<ul>";
foreach ($nest as $tstat) {
//    print_r($tstat);
    echo "<li>";
    echo "{$tstat['name']}: <strong style='font-size:150%'>{$tstat['ambient_temperature_f']}</strong> → {$tstat['target_temperature_f']} ({$tstat['hvac_state']})";
    echo "</li>";
}
echo "</ul>";

echo "<form action='".basename(__FILE__)."' method=POST><input type=hidden name=reset value=1><input type=submit value='Reset override status'></form>";

echo '</body></html>';

function getCustomEnv($file) {
    $contents = file_get_contents($file);
    preg_match_all('%(?P<key>.*)=(?P<val>.*)%', $contents, $matches);
    $res = [];
    foreach ($matches['key'] as $id => $key) {
        $res[$key] = $matches['val'][$id];
    }
    return $res;
}