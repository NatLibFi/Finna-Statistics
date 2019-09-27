<?php

namespace Finna\Stats\BaseAbstract;

abstract class BaseAbstract
{
    protected $pdo;

    protected $table;

    protected $output;

    protected $settings;

    public function __construct(\PDO $pdo, $settings) {
        $this->pdo = $pdo;
        $this->table = $settings['table'];
        $this->output = $settings['output'];
        $this->settings = $settings;
    }

    public abstract function run();

    public abstract function processResults($results);
}