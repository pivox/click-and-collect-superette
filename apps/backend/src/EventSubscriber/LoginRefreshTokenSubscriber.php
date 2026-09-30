<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\RefreshTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Extends the login payload (#616, additive — non-breaking for the PWA):
 * `refresh_token` (opaque, returned once, stored hashed) and `expires_in`
 * (access token TTL in seconds). Each login starts a new rotation family.
 */
final readonly class LoginRefreshTokenSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RefreshTokenManager $refreshTokenManager,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        #[Autowire(param: 'lexik_jwt_authentication.token_ttl')]
        private int $jwtTokenTtl,
        #[Autowire(service: 'monolog.logger.security')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User || null !== $user->getDeletedAt()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        $rawToken = $this->refreshTokenManager->issue(
            $user,
            deviceLabel: $this->extractDeviceLabel(),
            createdByIp: $request?->getClientIp(),
        );
        $this->entityManager->flush();

        $data = $event->getData();
        $data['refresh_token'] = $rawToken;
        $data['expires_in'] = $this->jwtTokenTtl;
        $event->setData($data);

        // Never log the raw token — only the fact a refresh token was issued.
        $this->logger->info('security.refresh_token.issued', [
            'user_id' => $user->getId()->toRfc4122(),
        ]);
    }

    /**
     * Optional `device_label` field in the login JSON body (ignored by
     * json_login itself). Free label chosen by the app, never a hardware id.
     */
    private function extractDeviceLabel(): ?string
    {
        $content = $this->requestStack->getCurrentRequest()?->getContent();
        if (null === $content || '' === $content) {
            return null;
        }

        try {
            $payload = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($payload) || !\is_string($payload['device_label'] ?? null)) {
            return null;
        }

        return RefreshTokenManager::normalizeDeviceLabel($payload['device_label']);
    }
}
