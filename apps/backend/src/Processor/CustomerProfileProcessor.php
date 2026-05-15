<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CustomerProfileOutput;
use App\Dto\CustomerProfilePatchInput;
use App\Entity\User;
use App\Provider\CustomerProfileProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<CustomerProfilePatchInput, CustomerProfileOutput>
 */
final readonly class CustomerProfileProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private CustomerProfileProvider $customerProfileProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerProfileOutput
    {
        if (!$data instanceof CustomerProfilePatchInput) {
            throw new \InvalidArgumentException('CustomerProfilePatchInput expected.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        if (null !== $data->firstName) {
            $user->setFirstName($data->firstName);
        }

        if (null !== $data->lastName) {
            $user->setLastName($data->lastName);
        }

        if (null !== $data->phone) {
            $user->setPhone($data->phone);
        }

        if (null !== $user->getFirstName() && null !== $user->getLastName()) {
            $user->setName($user->getFirstName().' '.$user->getLastName());
        }

        $this->entityManager->flush();

        return $this->customerProfileProvider->toOutput($user);
    }
}
