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
namespace Finna\Stats\UserListCounts;

class UserListCountStatistics
{
    /** @var \PDO The PDO instance used to access the correct database. */
    private $pdo;

    /** @var string Name of the table containing the correct data */
    private $table;

    /**
     * Creates a new instance of UserListCountStatistics.
     * @param \PDO $pdo The connection used to access the user database
     * @param string $table Name of the table containing the user data
     */
    public function __construct(\PDO $pdo, $table = 'user_list')
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function getUserListStats()
    {
        $sql = 'SELECT COUNT(*) as count, SUM(CASE WHEN public = 1 THEN 1 ELSE 0 END) as public FROM :table';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':table' => $this->table]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result;
    }
}