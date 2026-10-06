<?php

declare(strict_types=1);

namespace App\Tests\Functional\PostgreSQL;

use ApiPlatform\Metadata\Get;
use App\Entity\Order;
use App\Entity\OrderStatusLog;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Provider\AdminBetaMetricsProvider;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AdminBetaMetricsPostgreSqlTest extends KernelTestCase
{
    public function testAggregatedMetricsGroupTheSelectedStoreIdentifierOnPostgreSql(): void
    {
        if ('1' !== getenv('RUN_POSTGRES_TESTS')) {
            self::markTestSkipped('Opt in with RUN_POSTGRES_TESTS=1 and a PostgreSQL DATABASE_URL.');
        }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = (string) getenv('DATABASE_URL');
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        self::assertInstanceOf(PostgreSQLPlatform::class, $connection->getDatabasePlatform());
        $schema = 'beta_metrics_'.bin2hex(random_bytes(6));
        $quotedSchema = $connection->quoteSingleIdentifier($schema);
        $connection->executeStatement('CREATE SCHEMA '.$quotedSchema);
        $connection->executeStatement('SET search_path TO '.$quotedSchema);

        try {
            (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
            $customer = (new User())->setEmail('beta-metrics-postgres@example.test')->setPassword('test')->setName('Beta metrics customer')->setRoles(['ROLE_CUSTOMER']);
            $shop = (new Shop())->setName('Beta metrics PostgreSQL')->setSlug($schema)->setQrCodeToken($schema);
            $order = (new Order())->setCustomer($customer)->setShop($shop);
            foreach ([$customer, $shop, $order, new OrderStatusLog($order, OrderStatus::Submitted), new OrderStatusLog($order, OrderStatus::Completed)] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $metrics = self::getContainer()->get(AdminBetaMetricsProvider::class)->provide(new Get());

            self::assertSame(1, $metrics->submitted);
            self::assertSame(1, $metrics->completed);
            self::assertSame(1, $metrics->activeStores);
            self::assertSame(1, $metrics->activatedStores);
            self::assertCount(1, $metrics->stores);
            self::assertSame($shop->getId()->toRfc4122(), $metrics->stores[0]->storeId);
            self::assertNotNull($metrics->stores[0]->lastActivityAt);
        } finally {
            $entityManager->clear();
            $connection->executeStatement('SET search_path TO public');
            $connection->executeStatement('DROP SCHEMA '.$quotedSchema.' CASCADE');
        }
    }
}
