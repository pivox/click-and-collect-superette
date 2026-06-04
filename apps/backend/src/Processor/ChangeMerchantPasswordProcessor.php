<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MerchantPasswordChangeInput;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<MerchantPasswordChangeInput, null>
 */
final readonly class ChangeMerchantPasswordProcessor implements ProcessorInterface
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
        if (!$data instanceof MerchantPasswordChangeInput) {
            throw new \InvalidArgumentException('MerchantPasswordChangeInput expected.');
        }

        $merchant = $this->security->getUser();
        if (!$merchant instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_ACCESS_REQUIRED');
        }
        if (!$merchant->isActive()) {
            throw new AccessDeniedHttpException('MERCHANT_ACCOUNT_INACTIVE');
        }

        if (!$this->passwordHasher->isPasswordValid($merchant, $data->currentPassword)) {
            throw new UnprocessableEntityHttpException('MERCHANT_CURRENT_PASSWORD_INVALID');
        }

        $merchant->setPassword($this->passwordHasher->hashPassword($merchant, $data->newPassword));
        $this->entityManager->flush();

        return null;
    }
}
