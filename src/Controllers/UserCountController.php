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

namespace Finna\Stats\UserCount;

require_once(__DIR__ . '/../Abstracts/BaseAbstract.php');
use Finna\Stats\BaseAbstract\BaseAbstract as Base;

/**
 * Generates statistics about user accounts.
 */
class UserCountController extends Base
{
    /** @var string[] List of authentication methods retrieved from the database */
    private $authMethods = [];

    /** @var int|null Maximum number of seconds since last login or null for no limit */
    private $maxAge;

    public function run()
    {
        $this->setAuthMethods($this->settings['authMethods']);
        $results = $this->getAccountsByOrganisation($this->settings['institutions']);
        // Add statistics rows for active accounts
        if (!empty($this->settings['maxAge'])) {
            $this->setMaxAge($this->settings['maxAge']);
            $active = $this->getAccountsByOrganisation($this->settings['institutions']);

            foreach ($active as $key => $values) {
                $active[$key]['name'] = $values['name'] . ' - active';
            }

            $results = array_merge($results, $active);
        }
        return $results;
    }

    public function processResults($results)
    {
        // Save results in a csv file
        $handle = fopen($this->output, 'a');

        // E_WARNING is being emitted on false
        if ($handle !== false) {
            foreach ($results as $result) {
                $success = fputcsv($handle, array_merge(
                    [$result['date'], $result['name'], $result['total']],
                    array_values($result['types'])
                ));
                if ($success === false) {
                    trigger_error('Failed to write line to file: ' . $this->output, E_USER_WARNING);
                }
            }
            if (fclose($handle) === false) {
                trigger_error('Failed to close file: ' . $this->output, E_USER_WARNING);
            }
        }
    }

    /**
     * Sets the maximum number of seconds since last login for counted accounts.
     * @param int|null $seconds Maximum number of seconds since last login or null for no limit
     */
    public function setMaxAge($seconds)
    {
        $this->maxAge = $seconds === null ? null : (int) $seconds;
    }

    /**
     * Sets the authentication methods to look for in the database
     * @param string[] $authMethods Authentication methods to look for
     */
    public function setAuthMethods(array $authMethods)
    {
        $this->authMethods = $authMethods;
    }

    /**
     * Returns a list of authenticated methods used in the user table.
     * @return string[] List of authentication methods in the database
     */
    public function listAuthMethods()
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT `finna_auth_method` FROM `$this->table`
        ");
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
    }

    /**
     * Returns statistic about the user accounts for each institution.
     *
     * By default, the method will retrieve data for all institutions. The data
     * can be limited to a specific set of institutions by providing an array of
     * institution names.
     *
     * The returned array contains an array for each institution and one for
     * combined statistics. Each array contains the following keys:
     *
     *  - date  : Current date time
     *  - name  : Name of the institution (or 'total')
     *  - total : Total number of accounts
     *  - types : Number of accounts per authentication method (with key
     *            indicating the method)
     *
     * The types array contains key for each authentication method used in the
     * user table, even if the count is 0.
     *
     * @param string[] $institutions List of institutions or empty array for all
     * @return array Statistical data about the number of user accounts
     */
    public function getAccountsByOrganisation(array $institutions = [])
    {
        $methods = $this->authMethods === [] ? $this->listAuthMethods() : $this->authMethods;
        $emptyRow = [
            'date' => date('c'),
            'name' => '',
            'total' => 0,
            'types' => array_fill_keys(array_map('strtolower', $methods), 0),
        ];

        $total = $emptyRow;
        $total['name'] = 'total';
        $names = $institutions;

        // Populate the result array even for institutions with 0 accounts
        if (!$institutions) {
            $stmt = $this->pdo->query("
                SELECT DISTINCT SUBSTRING_INDEX(`username`, ':', 1) as `name`
                FROM `$this->table`
                ORDER BY `name`
            ");

            $names = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
        }

        $results = [];

        foreach ($names as $institution) {
            $key = strtolower($institution);

            $results[$key] = $emptyRow;
            $results[$key]['name'] = $institution;
        }

        list($sql, $params) = $this->getUserCountQuery($institutions);
        $stmt = $this->pdo->prepare($sql);
        $stmt->setFetchMode(\PDO::FETCH_NUM);
        $stmt->execute($params);

        foreach ($stmt as $row) {
            list($institution, $method, $count) = $row;
            $method = strtolower($method);
            $institution = strtolower($institution);

            $total['total'] += $count;
            $total['types'][$method] += $count;
            $results[$institution]['total'] += $count;
            $results[$institution]['types'][$method] += $count;
        }

        array_unshift($results, $total);
        return array_values($results);
    }

    /**
     * Creates the SQL query to fetch the user counts.
     * @param string[] $institutions List of institutions or empty array for all
     * @return array Array containing the SQL and the parameters
     */
    private function getUserCountQuery($institutions)
    {
        $params = [];
        $clauses = [];

        if ($this->authMethods !== []) {
            $methods = $this->authMethods;
            $clause = [];

            if (($key = array_search(null, $methods, true)) !== false) {
                $clause[] = '`auth_method` IS NULL';
                unset($methods[$key]);
            }

            if ($methods !== []) {
                $params = array_merge($params, $methods);
                $clause[] = sprintf(
                    "`auth_method` IN (%s)",
                    implode(', ', array_fill(0, count($methods), '?'))
                );
            }

            $clauses[] = sprintf('(%s)', implode(' OR ', $clause));
        }

        if ($institutions !== []) {
            $params = array_merge($params, $institutions);
            $clauses[] = sprintf(
                "SUBSTRING_INDEX(`username`, ':', 1) IN (%s)",
                implode(', ', array_fill(0, count($institutions), '?'))
            );
        }

        if ($this->maxAge !== null) {
            $clauses[] = 'DATE_ADD(`last_login`, INTERVAL ? SECOND) > NOW()';
            $params[] = $this->maxAge;
        }

        $where = count($clauses) === 0 ? '' : 'WHERE ' . implode(' AND ', $clauses);

        $sql = "
            SELECT SUBSTRING_INDEX(`username`, ':', 1), `auth_method`, COUNT(*)
            FROM `$this->table`
            $where
            GROUP BY SUBSTRING_INDEX(`username`, ':', 1), `auth_method`
        ";

        return [$sql, $params];
    }
}
