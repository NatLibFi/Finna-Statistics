<?php

namespace Finna\Stats\BaseAbstract;

abstract class BaseAbstract
{
    protected $pdo;

    protected $table;

    protected $outputFile;

    protected $settings;

    public function __construct(\PDO $pdo, $settings) {
        $this->pdo = $pdo;
        $this->table = $settings['table'];
        $this->settings = $settings;
    }

    public abstract function run();
}