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

require_once __DIR__ . '/UserCountStatistics.php';
require_once __DIR__ . '/../Utility/SettingsFile.php';

if (empty($argv[1]) || empty($argv[2])) {
    die('Usage user_counts.php <settings-file> <output-file>');
}

$settings = new \Finna\Stats\Utility\SettingsFile($argv[1]);
$db = $settings->loadDatabase();

// Fetch the total user account counts
$stats = new \Finna\Stats\UserCounts\UserCountStatistics($db, $settings['table']);
$stats->setAuthMethods($settings['authMethods']);
$results = $stats->getAccountsByOrganisation($settings['institutions']);

// Add statistics rows for active accounts
if (!empty($settings['maxAge'])) {
    $stats->setMaxAge($settings['maxAge']);
    $active = $stats->getAccountsByOrganisation($settings['institutions']);

    foreach ($active as $key => $values) {
        $active[$key]['name'] = $values['name'] . ' - active';
    }

    $results = array_merge($results, $active);
}

// Save results in a csv file
$handle = fopen($argv[2], 'a');

// E_WARNING is being emitted on false
if ($handle !== false) {
    foreach ($results as $result) {
        $success = fputcsv($handle, array_merge(
            [$result['date'], $result['name'], $result['total']],
            array_values($result['types'])
        ));
        if ($success === false) {
            trigger_error('Failed to write line to file in user_counts.php', E_USER_WARNING);
        }
    }
    fclose($handle);
}
