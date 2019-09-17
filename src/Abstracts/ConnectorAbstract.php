<?php

namespace Finna\Stats\Abstracts;

abstract class ConnectorAbstract {

    /** @var \PDO The PDO instance used to access the correct database. */
    private $pdo;

    /** @var string Name of the table containing the correct data */
    private $table;

    /** @var string[] List of authentication methods retrieved from the database */
    private $authMethods;

    /** @var int|null Maximum number of seconds since last login or null for no limit */
    private $maxAge;

    /**
     * Creates a new instance of ConnectorAbstract.
     * @param \PDO $pdo The connection used to access the user database
     * @param string $table Name of the table containing the user data
     */
    public function __construct(\PDO $pdo, $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->authMethods = [];
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
}