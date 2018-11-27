<?php
/**
 * Finna statistics utility scripts.
 * Copyright (c) 2018 University Of Helsinki (The National Library Of Finland)
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
 * @author    Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @copyright 2018 University Of Helsinki (The National Library Of Finland)
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0
 */

error_reporting(E_ALL);
date_default_timezone_set('UTC');

require_once __DIR__ . '/ViewStatistics.php';
require_once __DIR__ . '/../Utility/SettingsFile.php';

/**
 * Print usage info and exit.
 *
 * @return void
 */
function usage()
{
    die(
        'Usage list_sites.php <settings-file> 
           --date=<YYYY-MM-DD,YYYY-MM-DD>
           --ids=<piwik-ids> 
           --institutions=<institutions> 
           --output=<output-dir>
           --debug'
    );
}

/**
 * Print error message, usage info and exit.
 *
 * @param string $msg Error
 *
 * @return void
 */
function error($msg)
{
    echo("Error: $msg" . PHP_EOL . PHP_EOL);
    usage();
}

if (empty($argv[1])) {
    usage();
}

$arguments = array_slice($argv, 2);
$ids = $institutions = [];
$outputDir = sys_get_temp_dir();
$date = null;

$settings = new \Finna\Stats\Utility\SettingsFile($argv[1]);
$stats = new \Finna\Stats\ViewStatistics\ViewStatistics($settings);

foreach ($arguments as $arg) {
    list($name, $val) = explode('=', $arg);
    $name = substr($name, 2);
    if ($name === 'date') {
        $date = $val;
    } else if ($name === 'institutions') {
        $institutions = explode(',', $val);
    } else if ($name == 'ids') {
        $ids = explode(',', $val);
    } else if ($name == 'output') {
        $val = realpath($val);
        if (!is_dir($val)) {
            error("Non-existing output-dir: $val");
        }
        $outputDir = $val;
    } else if ($name == 'debug') {
        $stats->setDebug(true);
    } else {
        error("Unknown argument: $arg");
    }
}

if (!$date) {
    error("No date specified");
}

$viewStatistics = $stats->getStatistics($date, $outputDir, $institutions, $ids);
