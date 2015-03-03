<?php

error_reporting(E_ALL);

require_once __DIR__ . '/UserCountStatistics.php';
require_once __DIR__ . '/../Utility/SettingsFile.php';

if (empty($argv[1])) {
    die('Usage list_methods.php <settings-file>');
}

$settings = new \Finna\Stats\Utility\SettingsFile($argv[1]);
$db = $settings->loadDatabase();
$stats = new \Finna\Stats\UserCounts\UserCountStatistics($db, $settings['table']);

foreach ($stats->listAuthMethods() as $method) {
    echo ($method === null ? 'NULL' : $method) . PHP_EOL;
}
