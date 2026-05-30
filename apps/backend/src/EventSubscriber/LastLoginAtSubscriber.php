<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class LastLoginAtSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
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

        if (!$user instanceof User) {
            $this->logger->warning('security.login.skipped', ['reason' => 'not_a_user', 'class' => $user::class]);

            return;
        }

        if (null !== $user->getDeletedAt()) {
            $this->logger->warning('security.login.skipped', ['reason' => 'deleted_account', 'user_id' => $user->getId()->toRfc4122()]);

            return;
        }

        $this->logger->debug('security.login.event', ['user_id' => $user->getId()->toRfc4122()]);

        try {
            $user->setLastLoginAt(new \DateTimeImmutable());
            // User is already managed here; this flush persists the login timestamp.
            $this->entityManager->flush();
            $this->logger->info('security.login', ['user_id' => $user->getId()->toRfc4122()]);
        } catch (\Throwable $e) {
            $this->logger->error('security.login.update_failed', [
                'user_id' => $user->getId()->toRfc4122(),
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
