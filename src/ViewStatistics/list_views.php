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

if (empty($argv[1])) {
    die('Usage list_sites.php <settings-file>');
}

$settings = new \Finna\Stats\Utility\SettingsFile($argv[1]);

$stats = new \Finna\Stats\ViewStatistics\ViewStatistics($settings);
$sites = $stats->getAllViews();

echo sprintf('%d sites in total:', count($sites)) . PHP_EOL;
echo '---' . PHP_EOL;
foreach ($sites as $site) {
    echo sprintf(
        '%s/%s (Piwik id %d)',
        $site['institution'],
        $site['view'],
        $site['piwikId']
    ) . PHP_EOL;
}
