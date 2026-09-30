<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\MerchantMembership;
use App\Entity\MerchantOrganization;
use App\Entity\Shop;
use App\Entity\User;
use App\Repository\MerchantMembershipRepository;
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

    public function testActiveMembershipOfShopOrganizationIsAllowed(): void
    {
        $primary = $this->merchant('primary@example.com');
        $secondary = $this->merchant('secondary@example.com');
        $organization = (new MerchantOrganization())->setName('Org')->setPrimaryAccount($primary);
        $membership = (new MerchantMembership())->setOrganization($organization)->setUser($secondary)->activate();
        $shop = (new Shop())->setOwner($primary)->setMerchantOrganization($organization);

        $this->checker(isMerchant: true, user: $secondary, membership: $membership)
            ->denyUnlessMerchantOwnsShop($shop);

        $this->addToAssertionCount(1);
    }

    public function testMembershipOfAnotherOrganizationIsDenied(): void
    {
        $primary = $this->merchant('primary@example.com');
        $foreign = $this->merchant('foreign@example.com');
        $organization = (new MerchantOrganization())->setName('Org')->setPrimaryAccount($primary);
        $otherOrganization = (new MerchantOrganization())->setName('Autre')->setPrimaryAccount($foreign);
        $membership = (new MerchantMembership())->setOrganization($otherOrganization)->setUser($foreign)->activate();
        $shop = (new Shop())->setOwner($primary)->setMerchantOrganization($organization);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('MERCHANT_CATALOG_FORBIDDEN');

        $this->checker(isMerchant: true, user: $foreign, membership: $membership)
            ->denyUnlessMerchantOwnsShop($shop);
    }

    public function testOwnerWithoutMembershipIsDeniedOnceShopHasOrganization(): void
    {
        // Once a shop belongs to an organization, ownership alone no longer grants access.
        $owner = $this->merchant('owner@example.com');
        $organization = (new MerchantOrganization())->setName('Org')->setPrimaryAccount($owner);
        $shop = (new Shop())->setOwner($owner)->setMerchantOrganization($organization);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('MERCHANT_CATALOG_FORBIDDEN');

        $this->checker(isMerchant: true, user: $owner, membership: null)->denyUnlessMerchantOwnsShop($shop);
    }

    public function testInactiveOrganizationIsDenied(): void
    {
        $owner = $this->merchant('owner@example.com');
        $organization = (new MerchantOrganization())->setName('Org')->setPrimaryAccount($owner)->setActive(false);
        $membership = (new MerchantMembership())->setOrganization($organization)->setUser($owner)->activate();
        $shop = (new Shop())->setOwner($owner)->setMerchantOrganization($organization);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('MERCHANT_CATALOG_FORBIDDEN');

        $this->checker(isMerchant: true, user: $owner, membership: $membership)
            ->denyUnlessMerchantOwnsShop($shop);
    }

    private function merchant(string $email): User
    {
        return (new User())
            ->setEmail($email)
            ->setPassword('hashed-password')
            ->setName('Merchant')
            ->setRoles(['ROLE_MERCHANT']);
    }

    private function checker(bool $isMerchant, ?User $user, ?MerchantMembership $membership = null): MerchantShopAccessChecker
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isMerchant);
        $security->method('getUser')->willReturn($user);

        $membershipRepository = $this->createStub(MerchantMembershipRepository::class);
        $membershipRepository->method('findOneActiveByUser')->willReturn($membership);

        return new MerchantShopAccessChecker($security, $membershipRepository);
    }
}
