<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MerchantFirstLoginPasswordChangeInput;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<MerchantFirstLoginPasswordChangeInput, null>
 */
final readonly class ChangeMerchantFirstLoginPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$data instanceof MerchantFirstLoginPasswordChangeInput) {
            throw new \InvalidArgumentException('MerchantFirstLoginPasswordChangeInput expected.');
        }

        $merchant = $this->security->getUser();
        if (!$merchant instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_ACCESS_REQUIRED');
        }
        if (!$merchant->isActive()) {
            throw new AccessDeniedHttpException('MERCHANT_ACCOUNT_INACTIVE');
        }
        if (!$merchant->isPasswordChangeRequired()) {
            throw new AccessDeniedHttpException('MERCHANT_PASSWORD_CHANGE_NOT_REQUIRED');
        }
        if ($data->newPassword !== $data->newPasswordConfirmation) {
            throw new UnprocessableEntityHttpException('MERCHANT_PASSWORD_CONFIRMATION_MISMATCH');
        }
        if (!$this->passwordHasher->isPasswordValid($merchant, $data->currentPassword)) {
            throw new UnprocessableEntityHttpException('MERCHANT_CURRENT_PASSWORD_INVALID');
        }

        $merchant
            ->setPassword($this->passwordHasher->hashPassword($merchant, $data->newPassword))
            ->setPasswordChangeRequired(false);
        $this->entityManager->flush();

        return null;
    }
}
