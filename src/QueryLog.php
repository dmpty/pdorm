<?php

namespace Dmpty\PdOrm;

class QueryLog
{
    private static bool $status = true;

    private static array $logs = [];

    private function __construct()
    {
    }

    public static function log(string $connection, string $sql, array $values): int
    {
        if (!static::$status) {
            return 0;
        }
        self::$logs[] = [
            'connection' => $connection ?: 'default',
            'sql' => $sql,
            'values' => $values,
        ];
        return count(self::$logs) - 1;
    }

    public static function logCost(int $index, float $cost): void
    {
        if (!static::$status) {
            return;
        }
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

    public static function setStatus(bool $status): void
    {
        static::$status = $status;
    }
}
