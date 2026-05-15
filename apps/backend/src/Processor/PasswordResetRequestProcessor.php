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
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (null !== $user && \in_array('ROLE_CUSTOMER', $user->getRoles(), true)) {
            $rawToken = $this->tokenManager->createForUser($user);
            $this->entityManager->flush();
            $this->tokenSender->send($user, $rawToken);
        }

        return new PasswordResetOutput(self::NEUTRAL_MESSAGE);
    }
}
