<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantAccountOutput;
use App\Dto\MerchantMeUpdateInput;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<MerchantMeUpdateInput, MerchantAccountOutput>
 */
final readonly class UpdateMerchantAccountProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantAccountOutput
    {
        if (!$data instanceof MerchantMeUpdateInput) {
            throw new \InvalidArgumentException('MerchantMeUpdateInput expected.');
        }

        $merchant = $this->currentMerchant();
        $payload = $this->currentPayload();

        $this->rejectForbiddenFields($payload);

        if (\array_key_exists('first_name', $payload) && null !== $data->firstName) {
            $merchant->setFirstName($data->firstName);
        }

        if (\array_key_exists('last_name', $payload) && null !== $data->lastName) {
            $merchant->setLastName($data->lastName);
        }

        if (\array_key_exists('phone', $payload)) {
            $merchant->setPhone($data->phone);
        }

        $this->refreshDisplayName($merchant);
        $this->entityManager->flush();

        return MerchantAccountOutput::fromUser($merchant);
    }

    private function currentMerchant(): User
    {
        $merchant = $this->security->getUser();
        if (!$merchant instanceof User) {
            throw new AccessDeniedHttpException('MERCHANT_ACCESS_REQUIRED');
        }
        if (!$merchant->isActive()) {
            throw new AccessDeniedHttpException('MERCHANT_ACCOUNT_INACTIVE');
        }

        return $merchant;
    }

    /**
     * @return array<string, mixed>
     */
    private function currentPayload(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || '' === $request->getContent()) {
            return [];
        }

        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function rejectForbiddenFields(array $payload): void
    {
        $forbiddenFields = [
            'id',
            'user_id',
            'name',
            'email',
            'roles',
            'active',
            'is_active',
            'status',
            'owner',
            'shopOwner',
            'shop_owner',
            'shopId',
            'shop_id',
            'password',
            'passwordHash',
            'password_hash',
            'plainPassword',
            'plain_password',
            'resetToken',
            'reset_token',
            'invitationToken',
            'invitation_token',
            'temporaryPassword',
            'temporary_password',
            'deletedAt',
            'deleted_at',
            'lastLoginAt',
            'last_login_at',
        ];

        foreach ($forbiddenFields as $field) {
            if (\array_key_exists($field, $payload)) {
                throw new UnprocessableEntityHttpException('MERCHANT_ACCOUNT_FIELD_FORBIDDEN');
            }
        }
    }

    private function refreshDisplayName(User $merchant): void
    {
        $name = trim(implode(' ', array_filter([
            $merchant->getFirstName(),
            $merchant->getLastName(),
        ], static fn (?string $part): bool => null !== $part && '' !== trim($part))));

        if ('' !== $name) {
            $merchant->setName($name);
        }
    }
}
