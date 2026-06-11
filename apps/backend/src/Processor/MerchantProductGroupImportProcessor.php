<?php

declare(strict_types=1);

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\MerchantProductGroupImportOutput;
use App\Dto\MerchantProductGroupImportInput;
use App\Service\MerchantProductGroupImporter;

/**
 * @implements ProcessorInterface<MerchantProductGroupImportInput, MerchantProductGroupImportOutput>
 */
final readonly class MerchantProductGroupImportProcessor implements ProcessorInterface
{
    public function __construct(
        private MerchantProductGroupImporter $importer,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MerchantProductGroupImportOutput
    {
        if (!$data instanceof MerchantProductGroupImportInput) {
            throw new \InvalidArgumentException('MerchantProductGroupImportInput expected.');
        }

        return $this->importer->import((string) ($uriVariables['storeId'] ?? ''), $data);
    }
}
