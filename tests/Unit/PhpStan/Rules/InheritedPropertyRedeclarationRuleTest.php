<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\PhpStan\Rules;

use Override;
use Phil\DevTools\DevTools;
use Phil\DevTools\PhpStan\Rules\InheritedPropertyRedeclarationRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @extends RuleTestCase<InheritedPropertyRedeclarationRule>
 */
#[CoversClass(InheritedPropertyRedeclarationRule::class)]
final class InheritedPropertyRedeclarationRuleTest extends RuleTestCase
{
    private const string FIXTURES = 'Phil\\DevTools\\Tests\\Fixtures\\PhpStan\\';

    private const string TIP = "Rename the property, or make the parent's protected and drop this declaration.";

    public function testAccepted(): void
    {
        $this->analyse([$this->fixture('accepted')], []);
    }

    public function testRefused(): void
    {
        $this->analyse([$this->fixture('refused')], [
            [$this->message('count', 'static'), 27, self::TIP],
            [$this->message('own', 'private'), 29, self::TIP],
            [$this->message('label', 'private'), 31, self::TIP],
        ]);
    }

    public function testTheSharedConfigurationLoadsTheRule(): void
    {
        self::assertStringContainsString('- '.InheritedPropertyRedeclarationRule::class, (string) file_get_contents(DevTools::directory().'/config/phpstan.neon'));
        self::assertStringContainsString('- '.InheritedPropertyRedeclarationRule::class, (string) file_get_contents(DevTools::directory().'/config/phpstan-witnesses.neon'));
    }

    public function testTheIdentifierIsStable(): void
    {
        self::assertSame('phil.propertyRedeclaration', InheritedPropertyRedeclarationRule::IDENTIFIER);
    }

    private function message(string $property, string $kind): string
    {
        return \sprintf('Property %sRefusedChild::$%s declares again the %s property %sRefusedBase::$%s: the two are distinct, and the parent never sees this one.', self::FIXTURES, $property, $kind, self::FIXTURES, $property);
    }

    #[Override]
    protected function getRule(): Rule
    {
        return new InheritedPropertyRedeclarationRule();
    }

    /**
     * The fixture, declared too, so that PHPStan finds its classes and their parents by reflection.
     */
    private function fixture(string $name): string
    {
        $file = DevTools::directory().'/tests/Fixtures/phpstan/property-redeclaration-'.$name.'.php';
        require_once $file;

        return $file;
    }
}
