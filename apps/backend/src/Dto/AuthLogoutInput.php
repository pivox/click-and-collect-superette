<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Logout is best-effort and idempotent: an unknown or already revoked token
 * still yields 204 (no token enumeration). `all: true` revokes every active
 * refresh token of the authenticated user (all devices).
 */
final readonly class AuthLogoutInput
{
    #[SerializedName('refresh_token')]
    public ?string $refreshToken;

    public bool $all;

    public function __construct(?string $refreshToken = null, bool $all = false)
    {
        $refreshToken = null === $refreshToken ? null : trim($refreshToken);
        $this->refreshToken = '' === $refreshToken ? null : $refreshToken;
        $this->all = $all;
    }
}
