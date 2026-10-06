<?php

declare(strict_types=1);

namespace App\Tests\Unit\Processor;

use ApiPlatform\Metadata\Post;
use App\Dto\MerchantPickupSlotCreateInput;
use App\Entity\PickupSlot;
use App\Entity\Shop;
use App\Entity\User;
use App\Processor\CreateMerchantPickupSlotProcessor;
use App\Repository\ExceptionalClosureRepository;
use App\Repository\MerchantMembershipRepository;
use App\Repository\PickupSlotRepository;
use App\Repository\ShopRepository;
use App\Security\MerchantShopAccessChecker;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class CreateMerchantPickupSlotProcessorTest extends TestCase
{
    public function testManualCreationLocksSameShopBeforeCheckingOverlap(): void
    {
        $merchant = new User();
        $shop = (new Shop())->setOwner($merchant);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($merchant);
        $security->method('isGranted')->willReturn(true);
        $checker = new MerchantShopAccessChecker($security, $this->createStub(MerchantMembershipRepository::class));
        $shops = $this->createStub(ShopRepository::class);
        $shops->method('find')->willReturn($shop);
        $slots = $this->createMock(PickupSlotRepository::class);
        $closures = $this->createStub(ExceptionalClosureRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $inTransaction = false;
        $locked = false;
        $connection = $this->createMock(Connection::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $connection->expects(self::once())->method('transactional')->willReturnCallback(
            static function (callable $work) use (&$inTransaction): mixed {
                $inTransaction = true;

                return $work();
            },
        );
        $entityManager->expects(self::once())->method('lock')->with($shop, LockMode::PESSIMISTIC_WRITE)->willReturnCallback(
            static function () use (&$inTransaction, &$locked): void {
                self::assertTrue($inTransaction);
                $locked = true;
            },
        );
        $slots->expects(self::once())->method('hasActiveOverlapForShop')->willReturnCallback(
            static function () use (&$locked): bool {
                self::assertTrue($locked, 'Manual creation must share the generator shop lock.');

                return false;
            },
        );
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(PickupSlot::class));
        $input = new MerchantPickupSlotCreateInput();
        $input->startsAt = new \DateTimeImmutable('2027-01-04T09:00:00+01:00');
        $input->endsAt = new \DateTimeImmutable('2027-01-04T10:00:00+01:00');
        $input->capacity = 5;
        (new CreateMerchantPickupSlotProcessor($shops, $slots, $closures, $checker, $entityManager))
            ->process($input, new Post(), ['storeId' => $shop->getId()->toRfc4122()]);
    }
}
