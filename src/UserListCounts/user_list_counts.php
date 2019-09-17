<?php

error_reporting(E_ALL);
date_default_timezone_set('UTC');

require_once __DIR__ . '/UserListCountStatistics.php';
require_once __DIR__ . '/../Utility/SettingsFile.php';

if (empty($argv[1]) || empty($argv[2])) {
    die('Usage user_list_counts.php <settings-file> <output-file>');
}

$settings = new \Finna\Stats\Utility\SettingsFile($argv[1]);
$db = $settings->loadDatabase();

$stats = new \Finna\Stats\UserCounts\UserListCountStatistics($db, $settings['table']);
$stats->setAuthMethods($settings['authMethods']);

// Add statistics rows for active accounts
if (!empty($settings['maxAge'])) {
    $stats->setMaxAge($settings['maxAge']);
    $active = $stats->getAccountsByOrganisation($settings['institutions']);

    foreach ($active as $key => $values) {
        $active[$key]['name'] = $values['name'] . ' - active';
    }

    $results = array_merge($results, $active);
}