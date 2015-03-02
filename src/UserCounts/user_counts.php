<?php

error_reporting(E_ALL);
date_default_timezone_set('UTC');

require_once __DIR__ . '/UserCountStatistics.php';
require_once __DIR__ . '/../Utility/SettingsFile.php';

if (empty($argv[1]) || empty($argv[2])) {
    die('Usage user_counts.php <settings-file> <output-file>');
}

$settings = new \Finna\Stats\Utility\SettingsFile($argv[1]);
$db = $settings->loadDatabase();

// Fetch the user account counts
$stats = new \Finna\Stats\UserCounts\UserCountStatistics($db, $settings['table']);
$stats->setAuthMethods($settings['authMethods']);
$results = $stats->getAccountsByOrganisation($settings['institutions']);

// Save results in a csv file
$handle = fopen($argv[2], 'a');

foreach ($results as $result) {
    fputcsv($handle, array_merge(
        [$result['date'], $result['name'], $result['total']],
        array_values($result['types'])
    ));
}

fclose($handle);
