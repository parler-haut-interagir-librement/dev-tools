<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\PhpStan\Rules;

use Override;
use Phil\DevTools\DevTools;
use Phil\DevTools\PhpStan\Rules\PsrLogContextRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @extends RuleTestCase<PsrLogContextRule>
 */
#[CoversClass(PsrLogContextRule::class)]
final class PsrLogContextRuleTest extends RuleTestCase
{
    private const string MESSAGE_TIP = 'A constant message lets a handler group the records of one event, and keeps each value in the context, where a processor can mask or drop it.';

    private const string CONTEXT_TIP = 'Pass a scalar, such as an identifier, or an array of scalars: each handler serialises the context its own way, and may keep or send it.';

    public function testConstantMessagesAndScalarContextsAreAccepted(): void
    {
        $this->analyse([$this->fixture('accepted')], []);
    }

    public function testBuiltMessagesAndObjectsInTheContextAreReported(): void
    {
        $this->analyse([$this->fixture('refused')], [
            [$this->message('info', 'interpolation'), 19, self::MESSAGE_TIP],
            [$this->message('error', 'concatenation'), 20, self::MESSAGE_TIP],
            [$this->message('warning', 'formatting'), 21, self::MESSAGE_TIP],
            [$this->message('log', 'formatting'), 22, self::MESSAGE_TIP],
            [$this->context('debug', 'DateTimeImmutable under "date"'), 23, self::CONTEXT_TIP],
            [$this->context('debug', 'DateTimeImmutable under "order" > "placed"'), 24, self::CONTEXT_TIP],
            [$this->context('debug', 'Stringable under "either"'), 25, self::CONTEXT_TIP],
            [$this->context('debug', 'DateTimeImmutable under "dates"'), 26, self::CONTEXT_TIP],
            [$this->context('debug', 'object under "0"'), 27, self::CONTEXT_TIP],
            [$this->message('critical', 'concatenation'), 28, self::MESSAGE_TIP],
            [$this->context('alert', 'DateTimeImmutable under "date"'), 29, self::CONTEXT_TIP],
            [$this->message('log', 'interpolation'), 30, self::MESSAGE_TIP],
            [$this->context('log', 'DateTimeImmutable under "date"'), 30, self::CONTEXT_TIP],
            [$this->context('log', 'DateTimeImmutable under "date"'), 31, self::CONTEXT_TIP],
            [$this->message('warning', 'interpolation'), 32, self::MESSAGE_TIP],
            [$this->context('debug', 'DateTimeImmutable under "a" > "b" > "c" > "d"'), 33, self::CONTEXT_TIP],
        ]);
    }

    public function testTheIdentifiersAreStable(): void
    {
        self::assertSame('phil.psrLogMessage', PsrLogContextRule::MESSAGE_IDENTIFIER);
        self::assertSame('phil.psrLogContext', PsrLogContextRule::CONTEXT_IDENTIFIER);
    }

    public function testTheSharedConfigurationLoadsTheRule(): void
    {
        self::assertStringContainsString('- '.PsrLogContextRule::class, (string) file_get_contents(DevTools::directory().'/config/phpstan.neon'));
    }

    #[Override]
    protected function getRule(): Rule
    {
        return new PsrLogContextRule();
    }

    private function message(string $method, string $how): string
    {
        return \sprintf('The message of LoggerInterface::%s() is built by %s: write a constant message with placeholders, such as "Order {order} shipped.", and pass the values in the context.', $method, $how);
    }

    private function context(string $method, string $what): string
    {
        return \sprintf('The context of LoggerInterface::%s() holds an object of class %s: only a Throwable, under "exception", may be an object.', $method, $what);
    }

    private function fixture(string $name): string
    {
        return DevTools::directory().'/tests/Fixtures/phpstan/psr-log-'.$name.'.php';
    }
}
