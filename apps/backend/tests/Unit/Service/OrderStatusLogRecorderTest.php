<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Kadhia;
use App\Entity\Order;
use App\Entity\Shop;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\OrderStatusActorType;
use App\Service\OrderStatusLogRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;

final class OrderStatusLogRecorderTest extends TestCase
{
    public function testAuthenticatedMerchantIsRecordedAsMerchantActor(): void
    {
        $merchant = $this->user(['ROLE_MERCHANT']);

        $log = $this->recorder($merchant)->record($this->order(), OrderStatus::Accepted);

        self::assertSame($merchant, $log->getActorUser());
        self::assertSame(OrderStatusActorType::Merchant, $log->getActorType());
    }

    public function testAuthenticatedCustomerIsRecordedAsCustomerActor(): void
    {
        $customer = $this->user(['ROLE_CUSTOMER']);

        $log = $this->recorder($customer)->record($this->order(), OrderStatus::Submitted);

        self::assertSame($customer, $log->getActorUser());
        self::assertSame(OrderStatusActorType::Customer, $log->getActorType());
    }

    public function testAuthenticatedAdminIsRecordedAsAdminActor(): void
    {
        $admin = $this->user(['ROLE_ADMIN']);

        $log = $this->recorder($admin)->record($this->order(), OrderStatus::Cancelled);

        self::assertSame(OrderStatusActorType::Admin, $log->getActorType());
    }

    public function testUnauthenticatedTransitionIsRecordedAsSystem(): void
    {
        $log = $this->recorder(null)->record($this->order(), OrderStatus::Cancelled);

        self::assertNull($log->getActorUser());
        self::assertSame(OrderStatusActorType::System, $log->getActorType());
    }

    public function testRecorderWithoutSecurityFallsBackToSystem(): void
    {
        $recorder = new OrderStatusLogRecorder($this->entityManager(), new NullLogger());

        $log = $recorder->record($this->order(), OrderStatus::Cancelled);

        self::assertSame(OrderStatusActorType::System, $log->getActorType());
    }

    public function testExplicitActorOverridesTheSecurityContext(): void
    {
        $merchant = $this->user(['ROLE_MERCHANT']);
        $explicit = $this->user(['ROLE_ADMIN']);

        $log = $this->recorder($merchant)->record(
            $this->order(),
            OrderStatus::Cancelled,
            actorUser: $explicit,
            actorType: OrderStatusActorType::Admin,
        );

        self::assertSame($explicit, $log->getActorUser());
        self::assertSame(OrderStatusActorType::Admin, $log->getActorType());
    }

    private function recorder(?User $user): OrderStatusLogRecorder
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new OrderStatusLogRecorder($this->entityManager(), new NullLogger(), $security);
    }

    private function entityManager(): EntityManagerInterface
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn([]);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        return $entityManager;
    }

    private function order(): Order
    {
        $shop = (new Shop())->setName('Supérette Test')->setSlug('test')->setQrCodeToken('token');
        $customer = $this->user(['ROLE_CUSTOMER']);
        $kadhia = (new Kadhia())->setCustomer($customer)->setShop($shop);

        return (new Order())->setCustomer($customer)->setShop($shop)->setKadhia($kadhia);
    }

    private function user(array $roles): User
    {
        return (new User())
            ->setEmail(uniqid('actor-', true).'@example.test')
            ->setPassword('hashed')
            ->setName('Actor')
            ->setRoles($roles);
    }
}
