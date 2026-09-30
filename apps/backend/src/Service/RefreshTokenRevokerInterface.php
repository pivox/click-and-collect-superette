<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

/**
 * Revokes every active refresh token of a user (password reset, admin
 * suspension, account deletion). Extracted as an interface so callers can be
 * unit-tested (final readonly classes cannot be mocked — pattern #9).
 */
interface RefreshTokenRevokerInterface
{
    /**
     * Marks all active refresh tokens of the user as revoked. Does not flush:
     * the caller owns the transaction boundary.
     */
    public function revokeAllForUser(User $user, ?\DateTimeImmutable $now = null): int;
}
