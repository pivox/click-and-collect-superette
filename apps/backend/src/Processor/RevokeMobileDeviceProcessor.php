<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Enum\MobileDeviceRevocationReason;
use App\Repository\MobileDeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * MOBILE-PUSH #620: DELETE /api/mobile/devices/{deviceId} — logical logout
 * revocation (revoked_at + reason `logout`), 204 idempotent on an already
 * revoked device; a device owned by another user yields a 404.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final readonly class RevokeMobileDeviceProcessor implements ProcessorInterface
{
    public function __construct(
        private MobileDeviceRepository $mobileDeviceRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
        #[Autowire(service: 'monolog.logger.notification')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('MOBILE_DEVICE_AUTHENTICATION_REQUIRED');
        }

        $deviceId = (string) ($uriVariables['deviceId'] ?? '');
        if (!Uuid::isValid($deviceId)) {
            throw new NotFoundHttpException('MOBILE_DEVICE_NOT_FOUND');
        }

        $device = $this->mobileDeviceRepository->find($deviceId);
        if (null === $device || !$device->getUser()->getId()->equals($user->getId())) {
            throw new NotFoundHttpException('MOBILE_DEVICE_NOT_FOUND');
        }

        $device->revoke(MobileDeviceRevocationReason::Logout);
        $this->entityManager->flush();
        $this->logger->info('mobile_device.revoked', [
            'device_id' => $device->getId()->toRfc4122(),
            'reason' => $device->getRevocationReason()?->value,
        ]);

        return null;
    }
}
