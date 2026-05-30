<?php

declare(strict_types=1);

namespace App\Tests\Unit\Monolog;

use App\Monolog\CorrelationIdProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class CorrelationIdProcessorTest extends TestCase
{
    private function makeRecord(): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: [],
        );
    }

    public function testInjectsCorrelationIdWhenSet(): void
    {
        $processor = new CorrelationIdProcessor();
        $processor->setCorrelationId('req-abc-123');

        $record = ($processor)($this->makeRecord());

        self::assertSame('req-abc-123', $record->extra['correlation_id']);
    }

    public function testDoesNotInjectWhenCorrelationIdIsNull(): void
    {
        $processor = new CorrelationIdProcessor();

        $record = ($processor)($this->makeRecord());

        self::assertArrayNotHasKey('correlation_id', $record->extra);
    }

    public function testResetsCorrelationId(): void
    {
        $processor = new CorrelationIdProcessor();
        $processor->setCorrelationId('req-first');
        $processor->setCorrelationId(null);

        $record = ($processor)($this->makeRecord());

        self::assertArrayNotHasKey('correlation_id', $record->extra);
    }

    public function testPreservesExistingExtraFields(): void
    {
        $processor = new CorrelationIdProcessor();
        $processor->setCorrelationId('req-xyz');

        $base = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'test',
            context: [],
            extra: ['existing_field' => 'value'],
        );

        $record = ($processor)($base);

        self::assertSame('value', $record->extra['existing_field']);
        self::assertSame('req-xyz', $record->extra['correlation_id']);
    }
}
