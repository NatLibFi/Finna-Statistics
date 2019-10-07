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

require_once __DIR__ . '/Utility/SettingsFile.php';

// Lets create an object of settingshandler
$settings = new \Finna\Stats\Utility\SettingsFile();
$properArguments = $settings->getProperArguments();

if (count($argv) === 1) {
    echo "No arguments given. Usage statistics_run.php Argument1 Argument2" . PHP_EOL;
    echo "Proper arguments are: " . PHP_EOL;
    foreach ($properArguments as $argument) {
        echo $argument . PHP_EOL;
    }
    echo "To use a specific method use Argument1=Method";
    die();
}

for ($i = 1; $i < count($argv); $i++) {
    $arg = $argv[$i];
    $method = explode("=", $arg, 2);
    if (count($method) > 1) {
        $arg = $method[0];
        $method = $method[1];
    } else {
        $method = false;
    }
    if (in_array($arg, $properArguments)) {
        $file = __DIR__ . '/Controllers/' . $arg . 'Controller.php';
        $name = '\\Finna\\Stats\\Controllers\\' . $arg . 'Controller';

        if (!file_exists($file)) {
            trigger_error("$file does not exist. Please check that settings matches a controller file.", E_USER_WARNING);
        } else {
            require_once($file);
        }

        if (!class_exists($name)) {
            trigger_error("$name does not exist. Please check that settings matches a controller class.", E_USER_WARNING);
        } else {
            $pdo = $settings->loadDatabase($arg);
            $obj = new $name($pdo, $settings->offsetGet($arg));
            if ($method !== false) {
                $obj->$method();
            } else {
                $result = $obj->run();
                if ($result) {
                    $obj->processResults($result);
                }
            }
        }
    } else {
        echo "Argument $arg is not a proper argument.";
    }
}
