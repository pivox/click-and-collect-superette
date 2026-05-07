<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\PlatformTheme;
use App\Entity\Shop;
use App\Entity\ShopTheme;
use App\Entity\User;
use App\Enum\ThemeFontFamily;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Uid\Uuid;

abstract class FunctionalApiTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->rebuildSchema();
        $this->createDefaultPlatformTheme();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->entityManager);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    protected function requestJson(string $method, string $path, ?array $payload = null, ?User $user = null): Response
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
        ];
        $content = null;

        if (null !== $payload) {
            $server['CONTENT_TYPE'] = 'application/json';
            $content = json_encode($payload, \JSON_THROW_ON_ERROR);
        }

        if (null !== $user) {
            $server['HTTP_X_TEST_USER'] = $user->getEmail();
        }

        $request = Request::create($path, $method, server: $server, content: $content);

        return self::$kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(Response $response): array
    {
        $content = $response->getContent();
        self::assertIsString($content);

        $decoded = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param list<string> $roles
     */
    protected function createUser(string $email, array $roles = []): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setRoles($roles)
            ->setPassword('test-password')
            ->setName('Test User');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function createShop(?User $owner = null, bool $active = true): Shop
    {
        $id = Uuid::v4();
        $shop = (new Shop())
            ->setName('Supérette Test '.$id)
            ->setSlug('superette-test-'.$id)
            ->setQrCodeToken('qr-'.$id)
            ->setOwner($owner)
            ->setActive($active);

        $this->entityManager->persist($shop);
        $this->entityManager->flush();

        return $shop;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createShopTheme(Shop $shop, array $overrides = []): ShopTheme
    {
        $theme = (new ShopTheme())->setShop($shop);
        $shop->setTheme($theme);
        $this->applyThemeOverrides($theme, $overrides);

        $this->entityManager->persist($theme);
        $this->entityManager->flush();

        return $theme;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected function validThemePayload(array $overrides = []): array
    {
        return array_replace([
            'primary_color' => '#123456',
            'secondary_color' => '#234567',
            'accent_color' => '#345678',
            'text_color' => '#456789',
            'background_color' => '#F8F9FA',
            'font_family' => 'cairo',
            'base_font_size' => 18,
        ], $overrides);
    }

    private function rebuildSchema(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        if ([] === $metadata) {
            return;
        }

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function createDefaultPlatformTheme(): PlatformTheme
    {
        $theme = new PlatformTheme();
        $this->setPrivateProperty($theme, 'id', Uuid::fromString(PlatformTheme::DEFAULT_ID));

        $this->entityManager->persist($theme);
        $this->entityManager->flush();

        return $theme;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function applyThemeOverrides(ShopTheme $theme, array $overrides): void
    {
        if (isset($overrides['primary_color'])) {
            $theme->setPrimaryColor((string) $overrides['primary_color']);
        }
        if (isset($overrides['secondary_color'])) {
            $theme->setSecondaryColor((string) $overrides['secondary_color']);
        }
        if (isset($overrides['accent_color'])) {
            $theme->setAccentColor((string) $overrides['accent_color']);
        }
        if (isset($overrides['text_color'])) {
            $theme->setTextColor((string) $overrides['text_color']);
        }
        if (isset($overrides['background_color'])) {
            $theme->setBackgroundColor((string) $overrides['background_color']);
        }
        if (isset($overrides['font_family'])) {
            $theme->setFontFamily(ThemeFontFamily::from((string) $overrides['font_family']));
        }
        if (isset($overrides['base_font_size'])) {
            $theme->setBaseFontSize((int) $overrides['base_font_size']);
        }
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setValue($object, $value);
    }
}
