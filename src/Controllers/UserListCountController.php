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
namespace Finna\Stats\Controllers;

require_once(__DIR__ . '/../Abstracts/BaseAbstract.php');

use Finna\Stats\BaseAbstract\BaseAbstract as Base;

class UserListCountController extends Base
{
    /**
     * Process given results, triggers a user warning if file can not be read
     * 
     * @param array $results, given results
     */
    public function run()
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) AS count,
            SUM(CASE WHEN public = 1 THEN 1 ELSE 0 END) as public
            FROM $this->table
        ");
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result;
    }

    public function processResults($results)
    {
        $time = ["time" => date("Y-m-d\TH:i:sP")];
        $results = array_merge($time, $results);

        $handle = fopen($this->output, 'a');

        // E_WARNING is being emitted on false
        if ($handle !== false) {
            $success = fputcsv($handle, $results);
            if ($success === false) {
                trigger_error('Failed to write line to file: ' . $this->output, E_USER_WARNING);
            }
            if (fclose($handle) === false) {
                trigger_error('Failed to close file: ' . $this->output, E_USER_WARNING);
            }
        }
    }
}
