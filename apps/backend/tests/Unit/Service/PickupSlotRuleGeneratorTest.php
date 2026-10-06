<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Shop;
use App\Repository\ExceptionalClosureRepository;
use App\Repository\PickupSlotRepository;
use App\Repository\PickupSlotRuleRepository;
use App\Service\PickupSlotRuleGenerator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PickupSlotRuleGeneratorTest extends TestCase
{
    public function testItLocksShopWithinTransactionBeforeReadingCandidates(): void
    {
        $shop = new Shop();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $rules = $this->createMock(PickupSlotRuleRepository::class);
        $slots = $this->createStub(PickupSlotRepository::class);
        $closures = $this->createMock(ExceptionalClosureRepository::class);
        $insideTransaction = false;
        $locked = false;
        $entityManager->expects(self::once())->method('wrapInTransaction')->willReturnCallback(
            static function (callable $work) use (&$insideTransaction): mixed {
                $insideTransaction = true;

                return $work();
            },
        );
        $entityManager->expects(self::once())->method('lock')->with($shop, LockMode::PESSIMISTIC_WRITE)->willReturnCallback(
            static function () use (&$insideTransaction, &$locked): void {
                self::assertTrue($insideTransaction);
                $locked = true;
            },
        );
        foreach ([$rules, $closures] as $repository) {
            $repository->expects(self::once())->method('findActiveForShop')->willReturnCallback(
                static function () use (&$locked): array {
                    self::assertTrue($locked, 'Candidate reads must happen after the shop lock is held.');

                    return [];
                },
            );
        }
        (new PickupSlotRuleGenerator($rules, $slots, $closures, $entityManager))->generateForShop($shop);
    }
}
