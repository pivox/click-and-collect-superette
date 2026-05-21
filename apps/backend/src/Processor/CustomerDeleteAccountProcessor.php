<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<object|null, null>
 */
final readonly class CustomerDeleteAccountProcessor implements ProcessorInterface
{
    private const ANONYMIZED_VALUE = '[supprimé]';

    public function __construct(
        private Security $security,
        private PasswordResetTokenRepository $passwordResetTokenRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $now = new \DateTimeImmutable();
        $user
            ->setDeletedAt($now)
            ->setActive(false)
            ->setPassword('*')
            ->setEmail($this->anonymizedEmail($user))
            ->setName(self::ANONYMIZED_VALUE)
            ->setFirstName(self::ANONYMIZED_VALUE)
            ->setLastName(self::ANONYMIZED_VALUE)
            ->setPhone(null);

        $this->passwordResetTokenRepository->consumeActiveTokensForUser($user, $now);
        $this->entityManager->flush();

        return null;
    }

    private function anonymizedEmail(User $user): string
    {
        return 'deleted-'.$user->getId()->toRfc4122().'@deleted.local';
    }
}
