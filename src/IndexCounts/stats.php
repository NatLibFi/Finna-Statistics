<?php

error_reporting(E_ALL);

if (empty($argv[1]) || !file_exists($argv[1]) || empty($argv[2])) {
    die('Usage stats.php <settings-file> <output-file>');
}

date_default_timezone_set('UTC');

require_once __DIR__ . '/StatsProcessor.php';

$processor = new \Finna\Stats\IndexCounts\StatsProcessor();
$settings = json_decode(file_get_contents($argv[1]), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die('Error parsing settings file: ' . $argv[1]);
}

$processor->setUrl($settings['url']);
$processor->setFilters($settings['filters']);
$processor->setQueries($settings['queries']);
$results = $processor->processFilterQueries($settings['filterSets']);

$handle = fopen($argv[2], 'a');
foreach ($results as $row) {
    fputcsv($handle, $row);
}
fclose($handle);
