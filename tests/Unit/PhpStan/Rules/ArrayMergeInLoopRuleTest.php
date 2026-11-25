<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\PhpStan\Rules;

use Override;
use Phil\DevTools\DevTools;
use Phil\DevTools\PhpStan\Rules\ArrayMergeInLoopRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @extends RuleTestCase<ArrayMergeInLoopRule>
 */
#[CoversClass(ArrayMergeInLoopRule::class)]
final class ArrayMergeInLoopRuleTest extends RuleTestCase
{
    private const string TIP = 'Each turn copies the whole array: a loop of n parts makes n² copies.';

    public function testAccepted(): void
    {
        $this->analyse([$this->fixture('accepted')], []);
    }

    public function testRefused(): void
    {
        $this->analyse([$this->fixture('refused')], [
            [$this->message('array_merge', '$all'), 25, self::TIP],
            [$this->message('array_merge', '$this->items'), 28, self::TIP],
            [$this->message('array_merge_recursive', 'self::$shared'), 33, self::TIP],
            [$this->message('array_merge', '$all'), 36, self::TIP],
            [$this->message('array_merge', '$all'), 43, self::TIP],
            [$this->message('array_merge', '$all'), 47, self::TIP],
            [$this->message('array_merge', '$all'), 64, self::TIP],
        ]);
    }

    public function testTheSharedConfigurationLoadsTheRule(): void
    {
        self::assertStringContainsString('- '.ArrayMergeInLoopRule::class, (string) file_get_contents(DevTools::directory().'/config/phpstan.neon'));
        self::assertStringContainsString('- '.ArrayMergeInLoopRule::class, (string) file_get_contents(DevTools::directory().'/config/phpstan-witnesses.neon'));
    }

    public function testTheIdentifierIsStable(): void
    {
        self::assertSame('phil.arrayMergeInLoop', ArrayMergeInLoopRule::IDENTIFIER);
    }

    private function message(string $function, string $target): string
    {
        return \sprintf('%s() merges %s into itself at each turn of the loop: collect the parts in a list and merge them once after it, or append with []=.', $function, $target);
    }

    #[Override]
    protected function getRule(): Rule
    {
        return new ArrayMergeInLoopRule();
    }

    private function fixture(string $name): string
    {
        return DevTools::directory().'/tests/Fixtures/phpstan/array-merge-'.$name.'.php';
    }
}
