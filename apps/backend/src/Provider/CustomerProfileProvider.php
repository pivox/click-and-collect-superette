<?php

declare(strict_types=1);

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CustomerProfileOutput;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<CustomerProfileOutput>
 */
final readonly class CustomerProfileProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomerProfileOutput
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        return $this->toOutput($user);
    }

    public function toOutput(User $user): CustomerProfileOutput
    {
        return new CustomerProfileOutput(
            $user->getId()->toRfc4122(),
            $user->getEmail(),
            ['ROLE_CUSTOMER'],
            $user->getFirstName(),
            $user->getLastName(),
            $user->getPhone(),
        );
    }
}
