<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\AdminMerchantTemporaryPasswordOutput;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AdminAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, AdminMerchantTemporaryPasswordOutput>
 */
final readonly class AdminResetMerchantTemporaryPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private AdminAuditLogger $auditLogger,
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminMerchantTemporaryPasswordOutput
    {
        $merchantId = (string) ($uriVariables['merchantId'] ?? '');
        $user = $this->resolveUser($merchantId);

        if (!\in_array('ROLE_MERCHANT', $user->getRoles(), true)) {
            throw new UnprocessableEntityHttpException('ADMIN_MERCHANT_TARGET_NOT_MERCHANT');
        }

        $temporaryPassword = bin2hex(random_bytes(18));
        $user->setPassword($this->passwordHasher->hashPassword($user, $temporaryPassword));
        $user->setPasswordChangeRequired(true);

        $this->auditLogger->log(
            action: 'merchant.temporary_password.reset',
            resourceType: 'merchant',
            resourceId: $user->getId()->toRfc4122(),
            summary: \sprintf('Mot de passe temporaire du marchand %s réinitialisé.', $user->getEmail()),
            metadata: ['email' => $user->getEmail()],
        );

        $this->entityManager->flush();

        $this->logger->info('merchant.temporary_password_reset', [
            'merchant_id' => $user->getId()->toRfc4122(),
        ]);

        return new AdminMerchantTemporaryPasswordOutput(
            merchantId: $user->getId()->toRfc4122(),
            temporaryPassword: $temporaryPassword,
        );
    }

    private function resolveUser(string $merchantId): User
    {
        if (!Uuid::isValid($merchantId)) {
            throw new NotFoundHttpException('ADMIN_MERCHANT_NOT_FOUND');
        }

        $user = $this->userRepository->find($merchantId);
        if (!$user instanceof User) {
            throw new NotFoundHttpException('ADMIN_MERCHANT_NOT_FOUND');
        }

        return $user;
    }
}
