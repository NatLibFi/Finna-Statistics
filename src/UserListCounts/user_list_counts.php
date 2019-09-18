<?php
/**
 * Finna statistics utility scripts.
 * Copyright (c) 2019 University Of Helsinki (The National Library Of Finland)
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
 * @author    Juha Luoma <juha.luoma@helsinki.fi>
 * @copyright 2019 University Of Helsinki (The National Library Of Finland)
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0
 */

error_reporting(E_ALL);
date_default_timezone_set('UTC');

require_once __DIR__ . '/UserListCountStatistics.php';
require_once __DIR__ . '/../Utility/SettingsFile.php';

if (empty($argv[1]) || empty($argv[2])) {
    die('Usage user_list_counts.php <settings-file> <output-file>');
}

$settings = new \Finna\Stats\Utility\SettingsFile($argv[1]);
$db = $settings->loadDatabase();

$stats = new \Finna\Stats\UserListCounts\UserListCountStatistics($db, $settings['table']);
$result = $stats->getUserListStats();
$time = ["time" => date("Y-m-d\TH-i-s")];
$result = array_merge($time, $result);

$handle = fopen($argv[2], 'a');
fputcsv($handle, $result);
fclose($handle);
