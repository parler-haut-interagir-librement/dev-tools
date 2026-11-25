<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Merge;

use Phil\DevTools\Merge\MergeMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MergeMessage::class)]
final class MergeMessageTest extends TestCase
{
    public function testOneBulletPerCommitOldestFirst(): void
    {
        self::assertSame(
            "Fusion du lot r72-merge-lot\n\n- Commande merge-lot\n- Tests : verrou repris\n",
            MergeMessage::build('r72-merge-lot', [' Commande merge-lot ', '', 'Tests : verrou repris', "\n"]),
        );
    }

    public function testWithoutCommitsOnlyTheSubject(): void
    {
        self::assertSame("Fusion du lot r1\n", MergeMessage::build('r1', ['', ' ']));
        self::assertSame('Fusion du lot r1', MergeMessage::subject('r1'));
    }

    #[DataProvider('branches')]
    public function testTheLotOfABranch(string $branch, string $lot): void
    {
        self::assertSame($lot, MergeMessage::lotOf($branch));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function branches(): iterable
    {
        yield ['b/r72-merge-lot', 'r72-merge-lot'];
        yield ['r72-merge-lot', 'r72-merge-lot'];
        yield ['b/', 'b/'];
        yield ['feature/b/x', 'feature/b/x'];
    }
}
