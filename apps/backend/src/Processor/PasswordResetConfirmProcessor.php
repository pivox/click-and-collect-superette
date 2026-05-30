<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\PasswordResetConfirmInput;
use App\Service\PasswordResetTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<PasswordResetConfirmInput, null>
 */
final readonly class PasswordResetConfirmProcessor implements ProcessorInterface
{
    public function __construct(
        private PasswordResetTokenManager $tokenManager,
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
        if (!$data instanceof PasswordResetConfirmInput) {
            throw new \InvalidArgumentException('PasswordResetConfirmInput expected.');
        }

        $this->logger->debug('security.password_reset.confirm.start');

        try {
            $this->tokenManager->confirm($data->token, $data->newPassword);
            $this->entityManager->flush();
            $this->logger->info('security.password_reset.confirmed');
        } catch (BadRequestHttpException $e) {
            // Expected business failures — invalid, used or expired token
            $this->logger->warning('security.password_reset.token_invalid', [
                'reason' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('security.password_reset.infrastructure_failed', [
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return null;
    }
}
