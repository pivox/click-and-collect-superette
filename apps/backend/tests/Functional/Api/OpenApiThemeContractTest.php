<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

final class OpenApiThemeContractTest extends FunctionalApiTestCase
{
    public function testOpenApiDocumentsRealThemeRoutesOnly(): void
    {
        $request = \Symfony\Component\HttpFoundation\Request::create(
            '/api/docs.jsonopenapi',
            'GET',
            server: ['HTTP_ACCEPT' => 'application/vnd.openapi+json'],
        );
        $response = self::$kernel->handle($request);

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeJson($response);
        self::assertArrayHasKey('paths', $payload);

        $paths = $payload['paths'];
        self::assertIsArray($paths);

        $this->assertOpenApiOperationExists($paths, '/api/merchant/stores/{storeId}/theme', 'get');
        $this->assertOpenApiOperationExists($paths, '/api/merchant/stores/{storeId}/theme', 'put');
        $this->assertOpenApiOperationExists($paths, '/api/merchant/stores/{storeId}/catalog', 'get');
        $this->assertOpenApiOperationExists($paths, '/api/merchant/stores/{storeId}/catalog', 'post');
        $this->assertOpenApiOperationExists($paths, '/api/merchant/catalog/{merchantProductId}', 'patch');
        $this->assertOpenApiOperationExists($paths, '/api/merchant/catalog/{merchantProductId}', 'delete');
        $this->assertOpenApiOperationExists($paths, '/api/stores/{storeId}/theme', 'get');
        $this->assertOpenApiOperationExists($paths, '/api/admin/theme', 'get');
        $this->assertOpenApiOperationExists($paths, '/api/admin/theme', 'put');

        self::assertArrayNotHasKey('/api/stores/{storeId}/catalog', $paths);
        self::assertArrayNotHasKey('post', $paths['/api/stores/{storeId}/theme'] ?? []);
        self::assertArrayNotHasKey('put', $paths['/api/stores/{storeId}/theme'] ?? []);
        self::assertArrayNotHasKey('delete', $paths['/api/stores/{storeId}/theme'] ?? []);
    }

    /**
     * @param array<string, mixed> $paths
     */
    private function assertOpenApiOperationExists(array $paths, string $path, string $method): void
    {
        self::assertArrayHasKey($path, $paths);
        self::assertIsArray($paths[$path]);
        self::assertArrayHasKey($method, $paths[$path]);
    }
}
