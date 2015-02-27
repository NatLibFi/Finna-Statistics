<?php

namespace Finna\Stats\Utility;

class SettingsFile implements \ArrayAccess
{
    private $filename;
    private $settings;

    public function __construct($file)
    {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException("The settings file '$file' does not exist'");
        }

        if (!is_readable($file)) {
            throw new \RuntimeException("Cannot read the settings file '$file'");
        }

        $settings = json_decode(file_get_contents($file), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Error parsing settings: " . json_last_error_msg());
        }

        $this->filename = $file;
        $this->settings = $settings;
    }

    public function loadDatabase()
    {
        $db = new \PDO(
            sprintf("mysql:dbname=%s;host=%s;charset=utf8", $this['database'], $this['hostname']),
            $this['username'],
            $this['password'],
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . date('P') . "'",
            ]
        );

        $db->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

        return $db;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->settings);
    }

    public function offsetGet($offset)
    {
        if (!isset($this[$offset])) {
            throw new \InvalidArgumentException("Invalid setting name '$offset'");
        }
        return $this->settings[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->settings[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->settings[$offset]);
    }
}
