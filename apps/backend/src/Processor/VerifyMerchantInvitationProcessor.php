<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantInvitationOutput;
use App\Dto\MerchantInvitationTokenInput;
use App\Service\MerchantInvitationTokenManager;

/**
 * @implements ProcessorInterface<MerchantInvitationTokenInput, MerchantInvitationOutput>
 */
final readonly class VerifyMerchantInvitationProcessor implements ProcessorInterface
{
    public function __construct(
        private MerchantInvitationTokenManager $tokenManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantInvitationOutput
    {
        if (!$data instanceof MerchantInvitationTokenInput) {
            throw new \InvalidArgumentException('MerchantInvitationTokenInput expected.');
        }

        return MerchantInvitationOutput::valid($this->tokenManager->verify($data->token));
    }
}
