<?php

namespace Dmpty\PdOrm;

use PDO;

class DB
{
    protected static ?DB $instance = null;

    private array $connections = [];

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    private static function getInstance(): DB
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public static function connect(PDO $pdo, string $connection = ''): void
    {
        $instance = static::getInstance();
        if (!count($instance->connections)) {
            $instance->connections['default'] = $pdo;
        }
        if (!$connection) {
            $instance->connections[$connection] = $pdo;
        }
    }

    public static function getPdo(string $connection = ''): PDO
    {
        $connection = $connection ?: 'default';
        $instance = static::getInstance();
        if (!isset($instance->connections[$connection])) {
            throw new PdOrmException('Connection ' . $connection . ' does not exist');
        }
        return $instance->connections[$connection];
    }

    public static function connection(string $connection): QueryBuilder
    {
        return new QueryBuilder(['connection' => $connection]);
    }

    public static function table(string $table): QueryBuilder
    {
        return (new QueryBuilder)->table($table);
    }

    public static function execute(string $sql, array $values = []): Collection|bool|int|string
    {
        return (new QueryBuilder)->executeRaw($sql, $values);
    }
}
