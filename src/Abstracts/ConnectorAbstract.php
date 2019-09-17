<?php

namespace Finna\Stats\Abstracts;

abstract class ConnectorAbstract {

    /** @var \PDO The PDO instance used to access the correct database. */
    private $pdo;

    /** @var string Name of the table containing the correct data */
    private $table;

    /**
     * Creates a new instance of ConnectorAbstract.
     * @param \PDO $pdo The connection used to access the user database
     * @param string $table Name of the table containing the user data
     */
    public function __construct(\PDO $pdo, $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }
}