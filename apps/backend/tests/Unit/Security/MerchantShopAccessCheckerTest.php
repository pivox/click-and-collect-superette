<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Shop;
use App\Entity\User;
use App\Security\MerchantShopAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class MerchantShopAccessCheckerTest extends TestCase
{
    public function testActiveOwnerIsAllowed(): void
    {
        $owner = $this->merchant('owner@example.com');
        $shop = (new Shop())->setOwner($owner);

        $this->checker(isMerchant: true, user: $owner)->denyUnlessMerchantOwnsShop($shop);

        $this->addToAssertionCount(1);
    }

    public function testSuspendedOwnerIsDenied(): void
    {
        $owner = $this->merchant('owner@example.com')->setActive(false);
        $shop = (new Shop())->setOwner($owner);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('MERCHANT_ACCOUNT_INACTIVE');

        $this->checker(isMerchant: true, user: $owner)->denyUnlessMerchantOwnsShop($shop);
    }

    public function testNonMerchantIsDenied(): void
    {
        $shop = (new Shop())->setOwner($this->merchant('owner@example.com'));

        $this->expectException(AccessDeniedHttpException::class);

        $this->checker(isMerchant: false, user: null)->denyUnlessMerchantOwnsShop($shop);
    }

    public function testNonOwnerIsDenied(): void
    {
        $owner = $this->merchant('owner@example.com');
        $other = $this->merchant('other@example.com');
        $shop = (new Shop())->setOwner($owner);

        $this->expectException(AccessDeniedHttpException::class);

        $this->checker(isMerchant: true, user: $other)->denyUnlessMerchantOwnsShop($shop);
    }

    private function merchant(string $email): User
    {
        return (new User())
            ->setEmail($email)
            ->setPassword('hashed-password')
            ->setName('Merchant')
            ->setRoles(['ROLE_MERCHANT']);
    }

    private function checker(bool $isMerchant, ?User $user): MerchantShopAccessChecker
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isMerchant);
        $security->method('getUser')->willReturn($user);

        return new MerchantShopAccessChecker($security);
    }
}
