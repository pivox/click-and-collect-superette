<?php

declare(strict_types=1);

namespace App\Provider;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

final class AdminBillingFilterParser
{
    public static function uuid(mixed $value, string $errorCode): ?Uuid
    {
        if (null === $value) {
            return null;
        }
        if (!\is_string($value) || !Uuid::isValid($value)) {
            throw new BadRequestHttpException($errorCode);
        }

        return Uuid::fromString($value);
    }

    public static function search(mixed $value, string $errorCode): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!\is_string($value) || mb_strlen($value) > 200) {
            throw new BadRequestHttpException($errorCode);
        }

        return '' === trim($value) ? null : trim($value);
    }

    public static function boolean(mixed $value, string $errorCode): ?bool
    {
        return match ($value) {
            null => null,
            'true', '1' => true,
            'false', '0' => false,
            default => throw new BadRequestHttpException($errorCode),
        };
    }
}
