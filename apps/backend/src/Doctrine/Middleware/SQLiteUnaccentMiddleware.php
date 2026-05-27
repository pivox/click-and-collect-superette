<?php

declare(strict_types=1);

namespace App\Doctrine\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Registers a no-op unaccent() SQLite UDF so that the UNACCENT DQL function
 * does not crash in the test environment (which uses SQLite instead of PostgreSQL).
 * On PostgreSQL the UDF registration is silently skipped.
 */
final class SQLiteUnaccentMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(#[\SensitiveParameter] array $params): Driver\Connection
            {
                $connection = parent::connect($params);

                try {
                    $native = $connection->getNativeConnection();
                    if ($native instanceof \PDO && 'sqlite' === $native->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
                        $native->sqliteCreateFunction('unaccent', static fn (string $s): string => $s, 1);
                    }
                } catch (\Throwable) {
                    // Not a PDO-backed SQLite connection — nothing to do.
                }

                return $connection;
            }
        };
    }
}
