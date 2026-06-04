<?php

declare(strict_types=1);

namespace App\Email;

final readonly class TransactionalEmail
{
    /**
     * @param list<string> $recipients
     */
    public function __construct(
        public array $recipients,
        public string $subject,
        public string $text,
    ) {
    }
}
