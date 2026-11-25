<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\PhpStan\Rules;

use Override;
use Phil\DevTools\DevTools;
use Phil\DevTools\PhpStan\Rules\DoctrineMappingTypeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @extends RuleTestCase<DoctrineMappingTypeRule>
 */
#[CoversClass(DoctrineMappingTypeRule::class)]
final class DoctrineMappingTypeRuleTest extends RuleTestCase
{
    private const string FIXTURES = 'Phil\\DevTools\\Tests\\Fixtures\\PhpStan\\';

    private const string DATE_TIP = 'Doctrine compares the object it loaded with the one it finds: a \\DateTime changed in place is the same object, and the change is never written.';

    private const string DECIMAL_TIP = 'A decimal column comes back as a string, which the first arithmetic turns into a float and its rounding.';

    public function testAccepted(): void
    {
        $this->analyse([$this->fixture('accepted')], []);
    }

    public function testRefused(): void
    {
        $this->analyse([$this->fixture('refused')], [
            [$this->column('startsAt', 'datetime', 'Types::DATETIME_IMMUTABLE'), 15, self::DATE_TIP],
            [$this->column('endsAt', 'datetime', 'Types::DATETIME_IMMUTABLE'), 18, self::DATE_TIP],
            [$this->decimal('price'), 21, self::DECIMAL_TIP],
            [$this->decimal('amount'), 24, self::DECIMAL_TIP],
            [$this->property('RefusedEvent', 'updatedAt'), 27, self::DATE_TIP],
            [$this->column('day', 'date', 'Types::DATE_IMMUTABLE'), 29, self::DATE_TIP],
            [$this->column('time', 'time', 'Types::TIME_IMMUTABLE'), 32, self::DATE_TIP],
            [$this->column('zoned', 'datetimetz', 'Types::DATETIMETZ_IMMUTABLE'), 35, self::DATE_TIP],
            [$this->property('RefusedEvent', 'createdAt'), 39, self::DATE_TIP],
            [$this->property('RefusedPeriod', 'from'), 48, self::DATE_TIP],
            [$this->property('RefusedMappedProperty', 'seen'), 54, self::DATE_TIP],
        ]);
    }

    public function testTheSharedConfigurationLoadsTheRule(): void
    {
        self::assertStringContainsString('- '.DoctrineMappingTypeRule::class, (string) file_get_contents(DevTools::directory().'/config/phpstan.neon'));
        self::assertStringContainsString('- '.DoctrineMappingTypeRule::class, (string) file_get_contents(DevTools::directory().'/config/phpstan-witnesses.neon'));
    }

    public function testTheIdentifiersAreStable(): void
    {
        self::assertSame('phil.doctrineMutableDate', DoctrineMappingTypeRule::MUTABLE_DATE_IDENTIFIER);
        self::assertSame('phil.doctrineDecimal', DoctrineMappingTypeRule::DECIMAL_IDENTIFIER);
    }

    private function column(string $property, string $type, string $instead): string
    {
        return \sprintf('Property %sRefusedEvent::$%s is mapped as the mutable type "%s": use %s and a \\DateTimeImmutable.', self::FIXTURES, $property, $type, $instead);
    }

    private function decimal(string $property): string
    {
        return \sprintf('Property %sRefusedEvent::$%s is mapped as "decimal": keep an integer of minor units, or a string column the domain parses.', self::FIXTURES, $property);
    }

    private function property(string $class, string $property): string
    {
        return \sprintf('Property %s%s::$%s is mapped with the mutable type \\DateTime: type it \\DateTimeImmutable, mapped as an immutable date type.', self::FIXTURES, $class, $property);
    }

    #[Override]
    protected function getRule(): Rule
    {
        return new DoctrineMappingTypeRule();
    }

    private function fixture(string $name): string
    {
        return DevTools::directory().'/tests/Fixtures/phpstan/doctrine-mapping-'.$name.'.php';
    }
}
