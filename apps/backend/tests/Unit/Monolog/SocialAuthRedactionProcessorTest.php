<?php

declare(strict_types=1);

namespace App\Tests\Unit\Monolog;

use App\Monolog\SocialAuthRedactionProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class SocialAuthRedactionProcessorTest extends TestCase
{
    public function testCallbackQueryIsRedactedFromRouterLogs(): void
    {
        $record = new LogRecord(new \DateTimeImmutable(), 'request', Level::Info, 'Matched route', ['request_uri' => 'https://api.test/api/auth/social/callback/google?code=secret&state=state']);
        $result = (new SocialAuthRedactionProcessor())($record);
        self::assertSame('https://api.test/api/auth/social/callback/google?[redacted]', $result->context['request_uri']);
    }
}
