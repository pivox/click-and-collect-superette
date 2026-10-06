<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\CustomerPasswordChangeInput;
use App\Entity\User;
use App\Service\RefreshTokenRevokerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<CustomerPasswordChangeInput, null>
 */
final readonly class ChangeCustomerPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private UserPasswordHasherInterface $passwordHasher,
        private RefreshTokenRevokerInterface $refreshTokenRevoker,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$data instanceof CustomerPasswordChangeInput) {
            throw new \InvalidArgumentException('CustomerPasswordChangeInput expected.');
        }

        $customer = $this->security->getUser();
        if (!$customer instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }
        if (!$customer->isActive()) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCOUNT_INACTIVE');
        }

        if ('' === $customer->getPassword()) {
            throw new UnprocessableEntityHttpException('CUSTOMER_PASSWORD_NOT_SET');
        }

        if (!$this->passwordHasher->isPasswordValid($customer, $data->currentPassword)) {
            throw new UnprocessableEntityHttpException('CUSTOMER_CURRENT_PASSWORD_INVALID');
        }

        $customer
            ->setPassword($this->passwordHasher->hashPassword($customer, $data->newPassword))
            ->setPasswordChangeRequired(false)
            ->clearTemporaryPasswordWindow();
        // #616: a password change invalidates every mobile session.
        $this->refreshTokenRevoker->revokeAllForUser($customer);
        $this->entityManager->flush();

        return null;
    }
}
