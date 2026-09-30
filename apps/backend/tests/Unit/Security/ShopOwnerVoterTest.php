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

    public function testDeactivatedAccountWithActiveMembershipIsDenied(): void
    {
        $member = $this->merchant('member@example.com')->setActive(false);
        $organization = (new \App\Entity\MerchantOrganization())
            ->setName('Org')
            ->setPrimaryAccount($member);
        $membership = (new \App\Entity\MerchantMembership())
            ->setOrganization($organization)
            ->setUser($member)
            ->activate();
        $shop = (new Shop())->setOwner($member)->setMerchantOrganization($organization);

        $result = $this->voterWithMembership($membership)->vote(
            $this->tokenReturningUser($member),
            $shop,
            [ShopOwnerVoter::SHOP_OWNER],
        );

        self::assertSame(Voter::ACCESS_DENIED, $result);

        // Control: the same membership grants access once the account is active,
        // proving the denial above comes from the isActive() guard.
        $member->setActive(true);

        $result = $this->voterWithMembership($membership)->vote(
            $this->tokenReturningUser($member),
            $shop,
            [ShopOwnerVoter::SHOP_OWNER],
        );

        self::assertSame(Voter::ACCESS_GRANTED, $result);
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

        $membershipRepository = $this->createStub(\App\Repository\MerchantMembershipRepository::class);
        $membershipRepository->method('findOneActiveByUser')->willReturn(null);

        return new ShopOwnerVoter($security, new \App\Security\MerchantShopAccessChecker($security, $membershipRepository));
    }

    private function voterWithMembership(\App\Entity\MerchantMembership $membership): ShopOwnerVoter
    {
        /** @var Security&MockObject $security */
        $security = $this->createMock(Security::class);
        $security
            ->expects(self::once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $membershipRepository = $this->createStub(\App\Repository\MerchantMembershipRepository::class);
        $membershipRepository->method('findOneActiveByUser')->willReturn($membership);

        return new ShopOwnerVoter($security, new \App\Security\MerchantShopAccessChecker($security, $membershipRepository));
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
