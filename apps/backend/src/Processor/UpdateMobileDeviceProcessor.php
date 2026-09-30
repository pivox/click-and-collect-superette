<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MobileDeviceResource;
use App\Dto\MobileDeviceUpdateInput;
use App\Entity\MobileDevice;
use App\Entity\User;
use App\Repository\MobileDeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * MOBILE-PUSH #620: PATCH /api/mobile/devices/{deviceId} — metadata update
 * (locale, timezone, app_version, enabled) + last_seen_at refresh. A device
 * owned by another user yields a 404 (no information leak).
 *
 * @implements ProcessorInterface<MobileDeviceUpdateInput, JsonResponse>
 */
final readonly class UpdateMobileDeviceProcessor implements ProcessorInterface
{
    public function __construct(
        private MobileDeviceRepository $mobileDeviceRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $device = $this->loadOwnedDevice((string) ($uriVariables['deviceId'] ?? ''));

        if (null !== $data->locale) {
            $device->setLocale($data->locale);
        }
        if (null !== $data->timezone) {
            $device->setTimezone($data->timezone);
        }
        if (null !== $data->appVersion) {
            $device->setAppVersion($data->appVersion);
        }
        if (null !== $data->enabled) {
            $device->setEnabled($data->enabled);
        }
        $device->markSeen();
        $this->entityManager->flush();

        return new JsonResponse(MobileDeviceResource::toPayload($device), 200);
    }

    private function loadOwnedDevice(string $deviceId): MobileDevice
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('MOBILE_DEVICE_AUTHENTICATION_REQUIRED');
        }

        if (!Uuid::isValid($deviceId)) {
            throw new NotFoundHttpException('MOBILE_DEVICE_NOT_FOUND');
        }

        $device = $this->mobileDeviceRepository->find($deviceId);
        if (null === $device || !$device->getUser()->getId()->equals($user->getId())) {
            throw new NotFoundHttpException('MOBILE_DEVICE_NOT_FOUND');
        }

        return $device;
    }
}
