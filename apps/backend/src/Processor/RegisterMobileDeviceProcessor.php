<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MobileDeviceResource;
use App\Dto\MobileDeviceRegisterInput;
use App\Entity\MobileDevice;
use App\Entity\User;
use App\Enum\MobileApplication;
use App\Enum\MobilePlatform;
use App\Enum\MobilePushProvider;
use App\Repository\MobileDeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * MOBILE-PUSH #620: POST /api/mobile/devices — upsert by push_token_hash
 * (decision 2): a token re-appearing under another user is reassigned to the
 * current user, re-enabled and un-revoked. 201 on creation, 200 on upsert.
 *
 * @implements ProcessorInterface<MobileDeviceRegisterInput, JsonResponse>
 */
final readonly class RegisterMobileDeviceProcessor implements ProcessorInterface
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
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('MOBILE_DEVICE_AUTHENTICATION_REQUIRED');
        }

        // Assert\NotBlank + Assert\Choice already ran (pattern #14).
        \assert(null !== $data->pushToken && null !== $data->application && null !== $data->platform);
        $application = MobileApplication::from($data->application);
        $platform = MobilePlatform::from($data->platform);
        $provider = MobilePushProvider::from($data->provider ?? MobilePushProvider::Expo->value);
        $locale = $data->locale ?? 'fr';

        // Decision 8: strict client/merchant separation — the requested
        // application must match the JWT role, 403 otherwise.
        $requiredRole = MobileApplication::Client === $application ? 'ROLE_CUSTOMER' : 'ROLE_MERCHANT';
        if (!$this->security->isGranted($requiredRole)) {
            throw new AccessDeniedHttpException('MOBILE_DEVICE_APPLICATION_ROLE_MISMATCH');
        }

        $hash = hash('sha256', $data->pushToken);
        $existing = $this->mobileDeviceRepository->findOneByTokenHash($hash);

        if (null !== $existing) {
            $existing->refresh(
                $user,
                $application,
                $platform,
                $provider,
                $locale,
                $data->timezone,
                $data->appVersion,
                $data->osMajorVersion,
            );
            $this->entityManager->flush();
            $this->logger->info('mobile_device.upserted', [
                'device_id' => $existing->getId()->toRfc4122(),
                'application' => $application->value,
            ]);

            return new JsonResponse(MobileDeviceResource::toPayload($existing), 200);
        }

        $device = new MobileDevice(
            user: $user,
            application: $application,
            platform: $platform,
            provider: $provider,
            pushToken: $data->pushToken,
            locale: $locale,
            timezone: $data->timezone,
            appVersion: $data->appVersion,
            osMajorVersion: $data->osMajorVersion,
        );
        $this->entityManager->persist($device);
        $this->entityManager->flush();
        $this->logger->info('mobile_device.registered', [
            'device_id' => $device->getId()->toRfc4122(),
            'application' => $application->value,
        ]);

        return new JsonResponse(MobileDeviceResource::toPayload($device), 201);
    }
}
