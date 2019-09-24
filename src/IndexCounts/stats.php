<?php
/**
 * Finna statistics utility scripts.
 * Copyright (c) 2015 University Of Helsinki (The National Library Of Finland)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Riikka Kalliomäki <riikka.kalliomaki@helsinki.fi>
 * @copyright 2015 University Of Helsinki (The National Library Of Finland)
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0
 */

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

// E_WARNING is being emitted on false
if ($handle !== false) {
    foreach ($results as $row) {
        $success = fputcsv($handle, $row);
        if ($success === false) {
            trigger_error('Failed to write line to file in stats.php', E_USER_WARNING);
        } 
    }
    fclose($handle);
}

