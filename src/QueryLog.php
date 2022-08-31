<?php

namespace Dmpty\PdOrm;

class QueryLog
{
    protected static ?QueryLog $instance = null;

    private array $logs = [];

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    private static function getInstance(): QueryLog
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public static function log(string $connection, string $sql, array $values, float $cost): void
    {
        $instance = static::getInstance();
        $instance->logs[] = [
            'connection' => $connection ?: 'default',
            'sql' => $sql,
            'values' => $values,
            'cost' => $cost,
        ];
    }

    public static function get(): array
    {
        return static::getInstance()->logs;
    }
}
