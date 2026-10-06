<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\CustomerProfileOutput;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Issue mobile #35 — records terms-of-service consent once, idempotently.
 *
 * @implements ProcessorInterface<null, CustomerProfileOutput>
 */
final readonly class AcceptCustomerTermsProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerProfileOutput
    {
        $customer = $this->security->getUser();
        if (!$customer instanceof User) {
            throw new AccessDeniedHttpException('CUSTOMER_ACCESS_REQUIRED');
        }

        if (null === $customer->getCguAcceptedAt()) {
            $customer->setCguAcceptedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }

        return CustomerProfileOutput::fromUser($customer);
    }
}
