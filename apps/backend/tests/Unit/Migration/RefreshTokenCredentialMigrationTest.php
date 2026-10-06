<?php

declare(strict_types=1);

namespace App\Tests\Unit\Migration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261003130000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RefreshTokenCredentialMigrationTest extends TestCase
{
    public function testMigrationRevokesLegacyTokensAndRollbackNeverReactivatesThem(): void
    {
        require_once \dirname(__DIR__, 3).'/migrations/Version20261003130000.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE refresh_tokens (id INTEGER PRIMARY KEY, revoked_at VARCHAR(64) DEFAULT NULL)');
        $connection->insert('refresh_tokens', ['id' => 1, 'revoked_at' => null]);
        $connection->insert('refresh_tokens', ['id' => 2, 'revoked_at' => '2026-10-01 10:00:00']);
        $migration = new Version20261003130000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
        $tokens = $connection->fetchAllAssociative('SELECT * FROM refresh_tokens ORDER BY id');
        self::assertNotNull($tokens[0]['revoked_at']);
        self::assertNull($tokens[0]['credential_hash']);
        self::assertSame('2026-10-01 10:00:00', $tokens[1]['revoked_at']);

        $rollback = new Version20261003130000($connection, new NullLogger());
        $rollback->down(new Schema());
        foreach ($rollback->getSql() as $query) {
            $connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
        self::assertSame($tokens[0]['revoked_at'], $connection->fetchOne('SELECT revoked_at FROM refresh_tokens WHERE id = 1'));
        $connection->close();
    }
}
