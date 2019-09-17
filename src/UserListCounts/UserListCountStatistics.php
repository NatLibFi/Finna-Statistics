<?php

namespace Finna\Stats\UserListCounts;
require_once __DIR__ . '/../Abstracts/ConnectorAbstract.php';

class UserListCountStatistics extends ConnectorAbstract
{
    //SELECT COUNT(*) AS count, SUM(CASE WHEN public = 1 THEN 1 ELSE 0 END) FROM user_list
    public function getUserListStats()
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) AS count,
            SUM(CASE WHEN public = 1 THEN 1 ELSE 0 END)
            FROM $this->table
        ");
        $stmt->execute();
        $result = $stmt->fetch();

        var_dump($result);
    }
}