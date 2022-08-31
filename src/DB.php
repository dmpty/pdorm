<?php

namespace Dmpty\PdOrm;

use Closure;
use PDO;

class DB
{
    protected static ?DB $instance = null;

    private array $preConnection = [];

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

    public static function connect(string $dsn, string $username, string $password, string $connection = ''): void
    {
        $connection = $connection ?: 'default';
        $instance = static::getInstance();
        $instance->preConnection[$connection] = [$dsn, $username, $password];
    }

    public static function getPdo(string $connection = ''): PDO
    {
        $connection = $connection ?: 'default';
        $instance = static::getInstance();
        if (isset($instance->connections[$connection])) {
            return $instance->connections[$connection];
        }
        if (isset($instance->preConnection[$connection])) {
            list($dsn, $username, $password) = $instance->preConnection[$connection];
            $pdo = new PDO($dsn, $username, $password);
            $instance->connections[$connection] = $pdo;
            return $pdo;
        }
        throw new PdOrmException('Connection ' . $connection . ' does not exist');
    }

    public static function connection(string $connection): Query
    {
        return new Query(['connection' => $connection]);
    }

    public static function table(string $table): Query
    {
        return (new Query)->table($table);
    }

    public static function execute(string $sql, array $values = []): Collection|bool|int|string
    {
        return (new Query)->executeRaw($sql, $values);
    }

    public static function transaction(Closure $callback)
    {
        return (new Query)->transaction($callback);
    }

    public static function getQueryLogs(): array
    {
        return QueryLog::get();
    }
}
