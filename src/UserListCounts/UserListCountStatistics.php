<?php

namespace Finna\Stats\UserListCounts;

class UserListCountStatistics
{
    /** @var \PDO The PDO instance used to access the correct database. */
    private $pdo;

    /** @var string Name of the table containing the correct data */
    private $table;

    /**
     * Creates a new instance of ConnectorAbstract.
     * @param \PDO $pdo The connection used to access the user database
     * @param string $table Name of the table containing the user data
     */
    public function __construct(\PDO $pdo, $table = 'user_list')
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }
    //SELECT COUNT(*) AS count, SUM(CASE WHEN public = 1 THEN 1 ELSE 0 END) FROM user_list
    public function getUserListStats()
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) AS count,
            SUM(CASE WHEN public = 1 THEN 1 ELSE 0 END) as public
            FROM $this->table
        ");
        $stmt->execute();
        $result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        var_dump($result);
    }
}