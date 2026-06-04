<?php

declare(strict_types=1);

namespace App\Email;

interface TransactionalEmailSenderInterface
{
    public function send(TransactionalEmail $email): void;
}
