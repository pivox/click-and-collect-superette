<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\OpenApi;

final class JwtOpenApiDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $securitySchemes = $openApi->getComponents()->getSecuritySchemes() ?? new \ArrayObject();
        $securitySchemes['bearerAuth'] = new \ArrayObject([
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ]);

        return $openApi
            ->withComponents(
                $openApi->getComponents()->withSecuritySchemes($securitySchemes)
            )
            ->withSecurity([['bearerAuth' => []]]);
    }
}
