<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
        #[Autowire(service: 'monolog.logger.security')]
        private LoggerInterface $logger,
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

        $userId = $user->getId()->toRfc4122();

        $this->logger->debug('security.account_delete.start', ['user_id' => $userId]);

        try {
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

            $this->logger->info('security.account_deleted', ['user_id' => $userId]);
        } catch (\Throwable $e) {
            $this->logger->error('security.account_delete.failed', [
                'user_id' => $userId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return null;
    }

    private function anonymizedEmail(User $user): string
    {
        return 'deleted-'.$user->getId()->toRfc4122().'@deleted.local';
    }
}
