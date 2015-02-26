<?php

error_reporting(E_ALL);

if (empty($argv[1]) || !file_exists($argv[1]) || empty($argv[2])) {
    die('Usage user_counts.php <settings-file> <output-file>');
}

$settings = json_decode(file_get_contents($argv[1]), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die('Error parsing settings file: ' . $argv[1]);
}

date_default_timezone_set('UTC');

require_once __DIR__ . '/UserCountStatistics.php';

// Initialize PDO in way the handles common issues
$db = new \PDO(
    sprintf("mysql:dbname=%s;host=%s;charset=utf8", $settings['database'], $settings['hostname']),
    $settings['username'],
    $settings['password'],
    [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . date('P') . "'",
    ]
);

$db->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

// Fetch the user account counts
$stats = new \Finna\Stats\UserCounts\UserCountStatistics($db, $settings['table']);
$methods = $stats->getAuthMethods();
$results = $stats->getAccountsByOrganisation($settings['institutions']);

// Save results in a csv file
$handle = fopen($argv[2], 'a');
fputcsv($handle, array_merge(['date', 'organisation', 'total'], $methods));

foreach ($results as $result) {
    fputcsv($handle, array_merge(
        [$result['date'], $result['name'], $result['total']],
        array_values($result['types'])
    ));
}

fclose($handle);
