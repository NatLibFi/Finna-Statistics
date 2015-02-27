<?php

error_reporting(E_ALL);
date_default_timezone_set('UTC');

require_once __DIR__ . '/StatsProcessor.php';
require_once __DIR__ . '/../Utility/SettingsFile.php';

if (empty($argv[1]) || !file_exists($argv[1]) || empty($argv[2])) {
    die('Usage stats.php <settings-file> <output-file>');
}

$processor = new \Finna\Stats\IndexCounts\StatsProcessor();
$settings = new \Finna\Stats\Utility\SettingsFile($argv[1]);

$processor->setUrl($settings['url']);
$processor->setFilters($settings['filters']);
$processor->setQueries($settings['queries']);
$results = $processor->processFilterQueries($settings['filterSets']);

$handle = fopen($argv[2], 'a');
foreach ($results as $row) {
    fputcsv($handle, $row);
}
fclose($handle);
