<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\ClientLogInput;
use App\Processor\ClientLogProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/client-logs',
            formats: ['json' => ['application/json']],
            input: ClientLogInput::class,
            output: false,
            status: 204,
            processor: ClientLogProcessor::class,
            validate: true,
        ),
    ],
)]
final readonly class ClientLogResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id = '',
    ) {
    }
}
