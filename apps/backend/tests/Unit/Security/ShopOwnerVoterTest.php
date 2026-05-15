<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Shop;
use App\Entity\User;
use App\Security\Voter\ShopOwnerVoter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ShopOwnerVoterTest extends TestCase
{
    public function testOwnerIsGranted(): void
    {
        $owner = $this->merchant('owner@example.com');
        $shop = (new Shop())->setOwner($owner);

        $result = $this->voterWithAdminFlag(false)->vote(
            $this->tokenReturningUser($owner),
            $shop,
            [ShopOwnerVoter::SHOP_OWNER],
        );

        self::assertSame(Voter::ACCESS_GRANTED, $result);
    }

    public function testNonOwnerIsDenied(): void
    {
        $owner = $this->merchant('owner@example.com');
        $otherMerchant = $this->merchant('other@example.com');
        $shop = (new Shop())->setOwner($owner);

        $result = $this->voterWithAdminFlag(false)->vote(
            $this->tokenReturningUser($otherMerchant),
            $shop,
            [ShopOwnerVoter::SHOP_OWNER],
        );

        self::assertSame(Voter::ACCESS_DENIED, $result);
    }

    public function testAdminIsGranted(): void
    {
        $merchant = $this->merchant('merchant@example.com');
        $shop = new Shop();

        $result = $this->voterWithAdminFlag(true)->vote(
            $this->tokenReturningUser($merchant),
            $shop,
            [ShopOwnerVoter::SHOP_OWNER],
        );

        self::assertSame(Voter::ACCESS_GRANTED, $result);
    }

    public function testAnonymousUserIsDenied(): void
    {
        $shop = (new Shop())->setOwner($this->merchant('owner@example.com'));

        $result = $this->voterWithAdminFlag(false)->vote(
            $this->tokenReturningUser(null),
            $shop,
            [ShopOwnerVoter::SHOP_OWNER],
        );

        self::assertSame(Voter::ACCESS_DENIED, $result);
    }

    public function testShopWithoutOwnerIsDeniedForMerchant(): void
    {
        $merchant = $this->merchant('merchant@example.com');
        $shop = new Shop();

        $result = $this->voterWithAdminFlag(false)->vote(
            $this->tokenReturningUser($merchant),
            $shop,
            [ShopOwnerVoter::SHOP_OWNER],
        );

        self::assertSame(Voter::ACCESS_DENIED, $result);
    }

    private function merchant(string $email): User
    {
        return (new User())
            ->setEmail($email)
            ->setPassword('hashed-password')
            ->setName('Merchant')
            ->setRoles(['ROLE_MERCHANT']);
    }

    private function voterWithAdminFlag(bool $isAdmin): ShopOwnerVoter
    {
        /** @var Security&MockObject $security */
        $security = $this->createMock(Security::class);
        $security
            ->expects(self::once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn($isAdmin);

        return new ShopOwnerVoter($security);
    }

    private function tokenReturningUser(mixed $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn($user);

        return $token;
    }
}
