<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\MobileDeviceRegisterInput;
use App\Dto\MobileDeviceUpdateInput;
use App\Entity\MobileDevice;
use App\Processor\RegisterMobileDeviceProcessor;
use App\Processor\RevokeMobileDeviceProcessor;
use App\Processor\UpdateMobileDeviceProcessor;

/**
 * MOBILE-PUSH #620: native mobile device registration endpoints.
 *
 * Role/application coherence (decision 8) is enforced in the processors:
 * ROLE_CUSTOMER may only register `client` devices, ROLE_MERCHANT only
 * `merchant` ones — 403 otherwise. The processors return the JSON payload
 * directly (201 create / 200 upsert on POST); push_token is never echoed.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/mobile/devices',
            formats: ['json' => ['application/json']],
            status: 201,
            input: MobileDeviceRegisterInput::class,
            output: false,
            read: false,
            processor: RegisterMobileDeviceProcessor::class,
            validate: true,
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_MERCHANT')",
        ),
        new Patch(
            uriTemplate: '/mobile/devices/{deviceId}',
            uriVariables: [
                // Pattern #1: the URI variable of a DTO-based resource points
                // to the resource class, never the entity.
                'deviceId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            input: MobileDeviceUpdateInput::class,
            output: false,
            read: false,
            processor: UpdateMobileDeviceProcessor::class,
            validate: true,
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_MERCHANT')",
        ),
        new Delete(
            uriTemplate: '/mobile/devices/{deviceId}',
            uriVariables: [
                'deviceId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            formats: ['json' => ['application/json']],
            status: 204,
            output: false,
            read: false,
            processor: RevokeMobileDeviceProcessor::class,
            security: "is_granted('ROLE_CUSTOMER') or is_granted('ROLE_MERCHANT')",
        ),
    ],
)]
final class MobileDeviceResource
{
    #[ApiProperty(identifier: true)]
    public ?string $id = null;

    /**
     * Shared JSON representation of a device — the push token is write-only
     * and never exposed.
     *
     * @return array<string, mixed>
     */
    public static function toPayload(MobileDevice $device): array
    {
        return [
            'id' => $device->getId()->toRfc4122(),
            'application' => $device->getApplication()->value,
            'platform' => $device->getPlatform()->value,
            'provider' => $device->getProvider()->value,
            'locale' => $device->getLocale(),
            'timezone' => $device->getTimezone(),
            'app_version' => $device->getAppVersion(),
            'os_major_version' => $device->getOsMajorVersion(),
            'enabled' => $device->isEnabled(),
            'last_seen_at' => $device->getLastSeenAt()->format(\DATE_ATOM),
            'revoked_at' => $device->getRevokedAt()?->format(\DATE_ATOM),
            'created_at' => $device->getCreatedAt()->format(\DATE_ATOM),
            'updated_at' => $device->getUpdatedAt()->format(\DATE_ATOM),
        ];
    }
}
