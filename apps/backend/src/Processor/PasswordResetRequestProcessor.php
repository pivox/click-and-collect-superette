<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\PasswordResetOutput;
use App\Dto\PasswordResetRequestInput;
use App\Repository\UserRepository;
use App\Service\PasswordResetTokenManager;
use App\Service\PasswordResetTokenSenderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<PasswordResetRequestInput, PasswordResetOutput>
 */
final readonly class PasswordResetRequestProcessor implements ProcessorInterface
{
    public const NEUTRAL_MESSAGE = 'Si un compte existe pour cet email, un lien de réinitialisation sera envoyé.';

    public function __construct(
        private UserRepository $userRepository,
        private PasswordResetTokenManager $tokenManager,
        private PasswordResetTokenSenderInterface $tokenSender,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.security')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PasswordResetOutput
    {
        if (!$data instanceof PasswordResetRequestInput) {
            throw new \InvalidArgumentException('PasswordResetRequestInput expected.');
        }

        $email = strtolower($data->email);
        $emailHash = hash('sha256', $email);

        // debug only — neutral, does not reveal user existence
        $this->logger->debug('security.password_reset.requested', ['email_hash' => $emailHash]);

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (null !== $user && \in_array('ROLE_CUSTOMER', $user->getRoles(), true) && null === $user->getDeletedAt()) {
            try {
                $rawToken = $this->tokenManager->createForUser($user);
                $this->entityManager->flush();
                $this->tokenSender->send($user, $rawToken);
                // info — no user identifier logged, only correlation hash
                $this->logger->info('security.password_reset.sent', ['email_hash' => $emailHash]);
            } catch (\Throwable $e) {
                $this->logger->error('security.password_reset.failed', [
                    'email_hash' => $emailHash,
                    'exception_class' => $e::class,
                    'exception_message' => $e->getMessage(),
                ]);
                throw $e;
            }
        } else {
            // debug — not warning, to avoid revealing user non-existence via log level
            $this->logger->debug('security.password_reset.user_not_eligible', ['email_hash' => $emailHash]);
        }

        return new PasswordResetOutput(self::NEUTRAL_MESSAGE);
    }
}
