<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Merge;

use Phil\DevTools\Merge\MergeFailure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MergeFailure::class)]
final class MergeFailureTest extends TestCase
{
    public function testEachStepHasItsExitCodeAndDescription(): void
    {
        $codes = [];

        foreach (MergeFailure::cases() as $failure) {
            $codes[$failure->value] = $failure->describe();
        }

        self::assertSame([
            2 => 'nothing to merge',
            3 => 'locked by another merge',
            4 => 'tracked files modified',
            5 => 'the rebase failed',
            6 => 'composer install failed',
            7 => 'a gate failed',
            8 => 'the merge failed',
            9 => 'the merge commit is not the expected one',
            10 => 'the cleanup failed',
        ], $codes);
    }
}
