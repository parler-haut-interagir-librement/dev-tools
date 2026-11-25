<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Merge;

use Phil\DevTools\Merge\LockHolder;
use Phil\DevTools\Merge\LockTakeover;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LockHolder::class)]
#[CoversClass(LockTakeover::class)]
final class LockHolderTest extends TestCase
{
    public function testItRoundTripsThroughJson(): void
    {
        $holder = new LockHolder(42, 'r72-merge-lot', 'b/r72-merge-lot', '2026-11-25T10:00:00+00:00');

        self::assertSame("{\"pid\":42,\"started\":\"2026-11-25T10:00:00+00:00\",\"lot\":\"r72-merge-lot\",\"branch\":\"b/r72-merge-lot\"}\n", $holder->toJson());
        self::assertEquals($holder, LockHolder::fromJson($holder->toJson()));
        self::assertSame('lot r72-merge-lot (branch b/r72-merge-lot, PID 42, started 2026-11-25T10:00:00+00:00)', $holder->describe());
    }

    #[DataProvider('unreadable')]
    public function testAnUnreadableLockHasNoProcess(string $json, LockHolder $expected): void
    {
        self::assertSame($expected->toJson(), LockHolder::fromJson($json)->toJson());
    }

    /**
     * @return iterable<string, array{string, LockHolder}>
     */
    public static function unreadable(): iterable
    {
        yield 'empty' => ['', new LockHolder(null, '?', '?', '?')];
        yield 'not an object' => ['"text"', new LockHolder(null, '?', '?', '?')];
        yield 'too deep' => ['{"pid":1,"lot":{"a":{"b":{"c":1}}}}', new LockHolder(null, '?', '?', '?')];
        yield 'wrong types' => ['{"pid":"12","lot":3,"branch":"","started":null}', new LockHolder(null, '?', '?', '?')];
        yield 'a nesting the parser still reads' => ['{"pid":7,"lot":"r1","x":{"a":{"b":1}}}', new LockHolder(7, 'r1', '?', '?')];
        yield 'a PID of zero' => ['{"pid":0,"lot":"r1"}', new LockHolder(null, 'r1', '?', '?')];
        yield 'a PID of one' => ['{"pid":1,"branch":"b/r1","started":"now"}', new LockHolder(1, '?', 'b/r1', 'now')];
    }

    public function testATakeoverSaysWhoAndWhy(): void
    {
        $takeover = new LockTakeover(new LockHolder(null, '?', '?', '?'), 'its process is gone');

        self::assertSame('Took over the merge lock of lot ? (branch ?, PID ?, started ?): its process is gone.', $takeover->describe());
    }
}
