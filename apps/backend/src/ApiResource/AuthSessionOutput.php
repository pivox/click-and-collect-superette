<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\AuthLogoutInput;
use App\Dto\AuthRefreshInput;
use App\Processor\AuthLogoutProcessor;
use App\Processor\AuthRefreshTokenProcessor;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Mobile session endpoints (#616): single-use refresh token rotation and
 * logout. The access JWT stays RS256 / 1 h / stateless — after logout it
 * remains valid until natural expiry (documented trade-off).
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/refresh',
            formats: ['json' => ['application/json']],
            input: AuthRefreshInput::class,
            status: 200,
            read: false,
            processor: AuthRefreshTokenProcessor::class,
        ),
        new Post(
            uriTemplate: '/auth/logout',
            formats: ['json' => ['application/json']],
            input: AuthLogoutInput::class,
            output: false,
            status: 204,
            read: false,
            processor: AuthLogoutProcessor::class,
        ),
    ],
)]
final readonly class AuthSessionOutput
{
    public function __construct(
        public string $token,
        #[SerializedName('refresh_token')]
        public string $refreshToken,
        #[SerializedName('expires_in')]
        public int $expiresIn,
    ) {
    }
}
