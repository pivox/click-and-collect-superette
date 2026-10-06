<?php

declare(strict_types=1);

namespace App\Tests\Functional\PostgreSQL;

use App\Entity\PickupSlot;
use App\Entity\PickupSlotRule;
use App\Entity\Shop;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

final class PickupSlotConcurrencyTest extends KernelTestCase
{
    #[DataProvider('concurrentActions')]
    public function testConcurrentCreationsAreSerializedPerShop(string $firstAction, string $secondAction): void
    {
        if ('1' !== getenv('RUN_POSTGRES_CONCURRENCY_TESTS')) {
            self::markTestSkipped('Opt in with RUN_POSTGRES_CONCURRENCY_TESTS=1 and a PostgreSQL DATABASE_URL.');
        }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = (string) getenv('DATABASE_URL');
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        self::assertInstanceOf(PostgreSQLPlatform::class, $connection->getDatabasePlatform());
        $schema = 'slot_concurrency_'.bin2hex(random_bytes(6));
        $quotedSchema = $connection->quoteSingleIdentifier($schema);
        $connection->executeStatement('CREATE SCHEMA '.$quotedSchema);
        $connection->executeStatement('SET search_path TO '.$quotedSchema);
        $workers = [];

        try {
            (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
            $merchant = (new User())->setEmail('slot-concurrency@example.test')->setRoles(['ROLE_MERCHANT'])->setPassword('test')->setName('Merchant');
            $shop = (new Shop())->setName('Concurrency')->setSlug($schema)->setQrCodeToken($schema)->setOwner($merchant);
            $startsAt = new \DateTimeImmutable('tomorrow 09:00', new \DateTimeZone('Africa/Tunis'));
            $rule = (new PickupSlotRule())->setShop($shop)->setWeekday((int) $startsAt->format('N'))
                ->setStartTime(new \DateTimeImmutable('1970-01-01 09:00:00'))
                ->setEndTime(new \DateTimeImmutable('1970-01-01 10:00:00'))->setCapacity(5);
            foreach ([$merchant, $shop, $rule] as $entity) {
                $em->persist($entity);
            }
            $em->flush();
            $connection->beginTransaction();
            $em->lock($shop, LockMode::PESSIMISTIC_WRITE);
            foreach ([$firstAction, $secondAction] as $index => $action) {
                $worker = new Process([
                    \PHP_BINARY, \dirname(__DIR__, 2).'/Support/pickup-slot-concurrency-worker.php',
                    $schema, $schema.'_'.$index, $shop->getId()->toRfc4122(), $action, $startsAt->format(\DateTimeInterface::ATOM),
                ], env: ['APP_ENV' => 'test', 'APP_DEBUG' => '1']);
                $worker->setTimeout(20);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 10;
            do {
                $connection->executeQuery('SELECT pg_stat_clear_snapshot()');
                $waitingQueries = $connection->fetchFirstColumn(
                    "SELECT query FROM pg_stat_activity WHERE application_name IN (?, ?) AND wait_event_type = 'Lock'",
                    [$schema.'_0', $schema.'_1'],
                );
                if (2 === \count($waitingQueries)) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            self::assertCount(2, $waitingQueries, implode("\n", array_map(static fn (Process $worker): string => $worker->getErrorOutput().$worker->getOutput(), $workers)));
            foreach ($waitingQueries as $query) {
                self::assertStringContainsString('FOR UPDATE', $query, 'Both requests must wait before checking existing slots.');
            }
            $connection->commit();

            $statuses = [];
            $generated = 0;
            foreach ($workers as $worker) {
                self::assertSame(0, $worker->wait(), $worker->getErrorOutput());
                $response = json_decode($worker->getOutput(), true, flags: \JSON_THROW_ON_ERROR);
                $statuses[] = $response['status'];
                self::assertContains($response['status'], [200, 201, 422], $worker->getOutput());
                $generated += $response['body']['generated_count'] ?? 0;
            }
            if ('manual' === $firstAction && 'manual' === $secondAction) {
                sort($statuses);
                self::assertSame([201, 422], $statuses);
            }
            $count = $em->getRepository(PickupSlot::class)->count(['shop' => $shop]);
            self::assertSame($generated + \count(array_filter($statuses, static fn (int $status): bool => 201 === $status)), $count);
            self::assertGreaterThan(0, $count);
            self::assertSame(0, (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM pickup_slots a JOIN pickup_slots b ON a.shop_id = b.shop_id AND a.id < b.id AND a.starts_at < b.ends_at AND a.ends_at > b.starts_at',
            ));
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(0);
                }
            }
            $connection->executeStatement('SET search_path TO public');
            $connection->executeStatement('DROP SCHEMA '.$quotedSchema.' CASCADE');
        }
    }

    public static function concurrentActions(): iterable
    {
        yield 'two generations' => ['generate', 'generate'];
        yield 'generation and manual slot' => ['generate', 'manual'];
        yield 'two manual slots' => ['manual', 'manual'];
    }
}
