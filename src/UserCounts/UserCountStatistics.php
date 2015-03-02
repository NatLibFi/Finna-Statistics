<?php

namespace Finna\Stats\UserCounts;

/**
 * Generates statistics about user accounts.
 */
class UserCountStatistics
{
    /** @var \PDO The PDO instance used to access the user database. */
    private $db;

    /** @var string Name of the table containing the user data */
    private $table;

    /** @var string[] List of authentication methods retrieved from the database */
    private $authMethods;

    /**
     * Creates a new instance of UserCountStatistics.
     * @param \PDO $db The connection used to access the user database
     * @param string $table Name of the table containing the user data
     */
    public function __construct(\PDO $db, $table = 'user')
    {
        $this->db = $db;
        $this->table = 'user';
        $this->authMethods = [];
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
        $stmt = $this->db->prepare("
            SELECT DISTINCT `authMethod` FROM `$this->table`
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
        $results = [];

        list($sql, $params) = $this->getUserCountQuery($institutions);
        $stmt = $this->db->prepare($sql);
        $stmt->setFetchMode(\PDO::FETCH_NUM);
        $stmt->execute($params);

        foreach ($stmt as $row) {
            list($name, $method, $count) = $row;
            $method = strtolower($method);

            if (!isset($results[$name])) {
                $results[$name] = $emptyRow;
                $results[$name]['name'] = $name;
            }

            $total['total'] += $count;
            $total['types'][$method] += $count;
            $results[$name]['total'] += $count;
            $results[$name]['types'][$method] += $count;
        }

        array_unshift($results, $total);
        return $results;
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
                $clause[] = '`authMethod` IS NULL';
                unset($methods[$key]);
            }

            if ($methods !== []) {
                $params = array_merge($params, $methods);
                $clause[] = sprintf(
                    "`authMethod` IN (%s)",
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

        $where = count($clauses) === 0 ? '' : 'WHERE ' . implode(' AND ', $clauses);

        $sql = "
            SELECT SUBSTRING_INDEX(`username`, ':', 1), `authMethod`, COUNT(*)
            FROM `$this->table`
            $where
            GROUP BY SUBSTRING_INDEX(`username`, ':', 1), `authMethod`
        ";

        return [$sql, $params];
    }
}
