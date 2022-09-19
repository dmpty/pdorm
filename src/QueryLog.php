<?php

namespace Dmpty\PdOrm;

class QueryLog
{
    private static array $logs = [];

    private function __construct()
    {
    }

    public static function log(string $connection, string $sql, array $values): int
    {
        self::$logs[] = [
            'connection' => $connection ?: 'default',
            'sql' => $sql,
            'values' => $values,
        ];
        return count(self::$logs) - 1;
    }

    public static function logCost(int $index, float $cost): void
    {
        $log = self::$logs[$index];
        $log['cost'] = $cost;
        self::$logs[$index] = $log;
    }

    public static function get(): array
    {
        return self::$logs;
    }

    public static function reset(): void
    {
        self::$logs = [];
    }
}
