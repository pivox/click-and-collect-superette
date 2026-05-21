<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\MerchantProduct;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\PasswordResetToken;
use App\Entity\ProductReference;
use App\Entity\User;
use App\Enum\ProductReferenceStatus;
use App\Enum\ProductUnit;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Service\PasswordResetTokenManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class CustomerDeleteAccountApiTest extends FunctionalApiTestCase
{
    public function testCustomerCanDeleteOwnAccountWithSoftDeleteAnonymizationAndTokenInvalidation(): void
    {
        $customer = $this->createCustomer('client.delete-account@example.test');
        $customerId = $customer->getId();
        $token = $this->createResetToken($customer);
        $order = $this->createCompletedOrder($customer);
        $orderId = $order->getId();

        $response = $this->requestJson('DELETE', '/api/me/account', user: $customer);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('', $response->getContent());

        $this->entityManager->clear();
        self::assertNull($this->findUserByEmail('client.delete-account@example.test'));
        $storedUser = $this->entityManager->getRepository(User::class)->find($customerId);
        self::assertInstanceOf(User::class, $storedUser);
        self::assertNotNull($storedUser->getDeletedAt());
        self::assertMatchesRegularExpression('/^deleted-[0-9a-f-]+@deleted\.local$/', $storedUser->getEmail());
        self::assertSame('[supprimé]', $storedUser->getName());
        self::assertSame('[supprimé]', $storedUser->getFirstName());
        self::assertSame('[supprimé]', $storedUser->getLastName());
        self::assertNull($storedUser->getPhone());
        self::assertSame('*', $storedUser->getPassword());

        $storedToken = $this->entityManager->getRepository(PasswordResetToken::class)->find($token->getId());
        self::assertInstanceOf(PasswordResetToken::class, $storedToken);
        self::assertNotNull($storedToken->getConsumedAt());

        $storedOrder = $this->entityManager->getRepository(Order::class)->find($orderId);
        self::assertInstanceOf(Order::class, $storedOrder);
        self::assertSame($storedUser->getId()->toRfc4122(), $storedOrder->getCustomer()->getId()->toRfc4122());
    }

    public function testDeletedCustomerCannotLoginAgain(): void
    {
        $customer = $this->createCustomer('client.delete-login@example.test');
        $customerId = $customer->getId();

        $this->requestJson('DELETE', '/api/me/account', user: $customer);

        $loginWithOriginalEmailResponse = $this->requestJson('POST', '/api/auth/login', [
            'email' => 'client.delete-login@example.test',
            'password' => 'secret123',
        ]);

        $this->entityManager->clear();
        $storedUser = $this->entityManager->getRepository(User::class)->find($customerId);
        self::assertInstanceOf(User::class, $storedUser);
        $loginWithAnonymizedEmailResponse = $this->requestJson('POST', '/api/auth/login', [
            'email' => $storedUser->getEmail(),
            'password' => 'secret123',
        ]);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $loginWithOriginalEmailResponse->getStatusCode());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $loginWithAnonymizedEmailResponse->getStatusCode());
    }

    public function testDeletedCustomerProfileIsNoLongerAccessible(): void
    {
        $customer = $this->createCustomer('client.delete-profile@example.test');

        $this->requestJson('DELETE', '/api/me/account', user: $customer);

        self::assertNotNull($customer->getDeletedAt());
        self::assertMatchesRegularExpression('/^deleted-[0-9a-f-]+@deleted\.local$/', $customer->getEmail());

        $response = $this->requestJson('GET', '/api/me/profile', user: $customer);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testAnonymousCannotDeleteAccount(): void
    {
        $response = $this->requestJson('DELETE', '/api/me/account');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testMerchantAndAdminCannotDeleteAccountThroughCustomerEndpoint(): void
    {
        $merchant = $this->createUser('merchant.delete-account@example.test', ['ROLE_MERCHANT']);
        $admin = $this->createUser('admin.delete-account@example.test', ['ROLE_ADMIN']);

        $merchantResponse = $this->requestJson('DELETE', '/api/me/account', user: $merchant);
        $adminResponse = $this->requestJson('DELETE', '/api/me/account', user: $admin);

        self::assertSame(Response::HTTP_FORBIDDEN, $merchantResponse->getStatusCode());
        self::assertSame(Response::HTTP_FORBIDDEN, $adminResponse->getStatusCode());
    }

    public function testPasswordResetRequestDoesNotCreateTokenForDeletedCustomer(): void
    {
        $customer = $this->createCustomer('client.delete-reset-request@example.test');
        $this->requestJson('DELETE', '/api/me/account', user: $customer);

        self::assertNotNull($customer->getDeletedAt());

        $response = $this->requestJson('POST', '/api/auth/password-reset/request', [
            'email' => $customer->getEmail(),
        ]);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertSame([], $this->activeResetTokens());
    }

    public function testSuccessfulLoginUpdatesLastLoginAt(): void
    {
        $customer = $this->createCustomer('client.last-login@example.test');
        self::assertNull($customer->getLastLoginAt());

        $response = $this->requestJson('POST', '/api/auth/login', [
            'email' => 'client.last-login@example.test',
            'password' => 'secret123',
        ]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->entityManager->clear();
        $storedUser = $this->findUserByEmail('client.last-login@example.test');
        self::assertInstanceOf(User::class, $storedUser);
        self::assertNotNull($storedUser->getLastLoginAt());
    }

    private function createCustomer(string $email): User
    {
        $customer = $this->createUser($email, ['ROLE_CUSTOMER'])
            ->setFirstName('Haythem')
            ->setLastName('Mabrouk')
            ->setName('Haythem Mabrouk')
            ->setPhone('+21600000000');

        $customer->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($customer, 'secret123'));

        $this->entityManager->flush();

        return $customer;
    }

    private function createResetToken(User $customer): PasswordResetToken
    {
        $token = new PasswordResetToken(
            $customer,
            PasswordResetTokenManager::hashToken('delete-token-'.$customer->getId()->toRfc4122()),
            new \DateTimeImmutable('+1 hour'),
        );

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return $token;
    }

    private function createCompletedOrder(User $customer): Order
    {
        $merchant = $this->createUser('merchant-delete-account-'.Uuid::v4()->toRfc4122().'@example.test', ['ROLE_MERCHANT']);
        $shop = $this->createShop($merchant);
        $product = $this->createMerchantProduct($shop);

        $line = (new OrderLine())
            ->setMerchantProduct($product)
            ->setQuantity(2)
            ->setUnitPriceTnd('2.500')
            ->setLineTotalTnd('5.000');

        $order = (new Order())
            ->setCustomer($customer)
            ->setShop($shop)
            ->addLine($line);
        $order->recomputeTotal();
        $order->submit();
        $order->accept();
        $order->startPreparing();
        $line->markPrepared(true);
        $order->markReady();
        $order->startPickup();
        $order->complete();

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    private function createMerchantProduct(\App\Entity\Shop $shop): MerchantProduct
    {
        $uniqueId = Uuid::v4()->toRfc4122();
        $brand = (new Brand())
            ->setCanonicalName('Marque '.$uniqueId)
            ->setSlug('brand-'.$uniqueId)
            ->setActive(true);
        $this->entityManager->persist($brand);

        $category = (new Category())
            ->setNameFr('Categorie '.$uniqueId)
            ->setSlug('cat-'.$uniqueId)
            ->setActive(true);
        $this->entityManager->persist($category);

        $productReference = (new ProductReference())
            ->setBrand($brand)
            ->setCategory($category)
            ->setNameFr('Produit '.$uniqueId)
            ->setUnit(ProductUnit::Piece)
            ->setStatus(ProductReferenceStatus::Approved);
        $this->entityManager->persist($productReference);

        $product = (new MerchantProduct())
            ->setShop($shop)
            ->setProductReference($productReference)
            ->setPriceTnd('2.500')
            ->setAvailable(true)
            ->setVisible(true);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    private function findUserByEmail(string $email): ?User
    {
        return self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
    }

    /**
     * @return list<PasswordResetToken>
     */
    private function activeResetTokens(): array
    {
        $tokens = self::getContainer()->get(PasswordResetTokenRepository::class)->findBy(['consumedAt' => null]);

        return array_values($tokens);
    }
}
