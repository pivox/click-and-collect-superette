<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MerchantInvitationCompleteInput;
use App\Service\MerchantInvitationTokenManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<MerchantInvitationCompleteInput, null>
 */
final readonly class CompleteMerchantInvitationProcessor implements ProcessorInterface
{
    public function __construct(
        private MerchantInvitationTokenManager $tokenManager,
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$data instanceof MerchantInvitationCompleteInput) {
            throw new \InvalidArgumentException('MerchantInvitationCompleteInput expected.');
        }

        if ($data->newPassword !== $data->newPasswordConfirmation) {
            throw new UnprocessableEntityHttpException('MERCHANT_INVITATION_PASSWORD_CONFIRMATION_MISMATCH');
        }

        $token = $this->tokenManager->complete($data->token, $data->newPassword);

        $this->logger->info('merchant.invitation_completed', [
            'merchant_id' => $token->getMerchant()->getId()->toRfc4122(),
        ]);

        return null;
    }
}
