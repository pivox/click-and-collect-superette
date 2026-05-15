<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CustomerProfileOutput;
use App\Dto\CustomerProfilePatchInput;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<CustomerProfilePatchInput, CustomerProfileOutput>
 */
final readonly class CustomerProfileProcessor implements ProcessorInterface
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
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerProfileOutput
    {
        if (!$data instanceof CustomerProfilePatchInput) {
            throw new \InvalidArgumentException('CustomerProfilePatchInput expected.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        $payload = $this->currentPayload();
        $nameProvided = \array_key_exists('name', $payload);

        if (\array_key_exists('first_name', $payload) && null !== $data->firstName) {
            $user->setFirstName($data->firstName);
        }

        if (\array_key_exists('last_name', $payload) && null !== $data->lastName) {
            $user->setLastName($data->lastName);
        }

        if ($nameProvided && null !== $data->name) {
            $user->setName($data->name);
        }

        if (\array_key_exists('phone', $payload)) {
            $user->setPhone($data->phone);
        }

        if (!$nameProvided && null !== $user->getFirstName() && null !== $user->getLastName()) {
            $user->setName($user->getFirstName().' '.$user->getLastName());
        }

        $this->entityManager->flush();

        return CustomerProfileOutput::fromUser($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentPayload(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        $content = $request->getContent();
        if ('' === $content) {
            return [];
        }

        $payload = json_decode($content, true);

        return \is_array($payload) ? $payload : [];
    }
}
