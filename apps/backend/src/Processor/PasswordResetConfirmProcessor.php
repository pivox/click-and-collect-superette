<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\PasswordResetConfirmInput;
use App\Service\PasswordResetTokenManager;

/**
 * @implements ProcessorInterface<PasswordResetConfirmInput, null>
 */
final readonly class PasswordResetConfirmProcessor implements ProcessorInterface
{
    public function __construct(
        private PasswordResetTokenManager $tokenManager,
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

        $this->tokenManager->confirm($data->token, $data->newPassword);

        return null;
    }
}
