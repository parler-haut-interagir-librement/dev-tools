<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\PhpStan\Rules;

use Override;
use Phil\DevTools\DevTools;
use Phil\DevTools\PhpStan\Rules\ClassNameMustEndWithComponentRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @extends RuleTestCase<ClassNameMustEndWithComponentRule>
 */
#[CoversClass(ClassNameMustEndWithComponentRule::class)]
final class ClassNameMustEndWithComponentRuleTest extends RuleTestCase
{
    private const string TIP = 'The suffix tells the role of the class at a glance, and keeps its name clear of the words PHP reserves, such as List.';

    /**
     * @var list<array{string, int, string}>
     */
    private const array REFUSED = [
        ['Class Alert carries #[AsTwigComponent]: its name must end with "Component", such as AlertComponent.', 13, self::TIP],
        ['Class Counter carries #[AsLiveComponent]: its name must end with "Component", such as CounterComponent.', 18, self::TIP],
        ['Class SecondInItsGroup carries #[AsLiveComponent]: its name must end with "Component", such as SecondInItsGroupComponent.', 24, self::TIP],
        ['Class ThroughAnAlias carries #[AsTwigComponent]: its name must end with "Component", such as ThroughAnAliasComponent.', 29, self::TIP],
        ['Class WrittenInLowerCase carries #[AsTwigComponent]: its name must end with "Component", such as WrittenInLowerCaseComponent.', 34, self::TIP],
        ['Class Lowercasecomponent carries #[AsTwigComponent]: its name must end with "Component", such as LowercasecomponentComponent.', 39, self::TIP],
    ];

    public function testAClassThatFollowsTheConventionIsAccepted(): void
    {
        $this->analyse([$this->fixture('accepted')], []);
    }

    public function testAComponentWithoutTheSuffixIsReportedOnTheLineOfItsName(): void
    {
        $this->analyse([$this->fixture('refused')], self::REFUSED);
    }

    public function testTheRuleRunsInAProjectWithoutSymfonyUx(): void
    {
        // Named as strings: the classes do not exist here, which is the point.
        foreach (['Symfony\\UX\\TwigComponent\\Attribute\\AsTwigComponent', 'Symfony\\UX\\LiveComponent\\Attribute\\AsLiveComponent'] as $attribute) {
            self::assertFalse(class_exists($attribute), 'This package must not install Symfony UX: the attribute is compared by its name.');
        }

        $this->analyse([$this->fixture('accepted'), $this->fixture('refused')], self::REFUSED);
    }

    public function testTheIdentifierIsStable(): void
    {
        self::assertSame('phil.componentClassName', ClassNameMustEndWithComponentRule::IDENTIFIER);
    }

    public function testTheSharedConfigurationLoadsTheRuleWithoutTheThirdPartyRules(): void
    {
        self::assertSame(['rules' => [ClassNameMustEndWithComponentRule::class]], require DevTools::directory().'/config/phpstan-symfony-ux.php');
    }

    #[Override]
    protected function getRule(): Rule
    {
        return new ClassNameMustEndWithComponentRule();
    }

    private function fixture(string $name): string
    {
        return DevTools::directory().'/tests/Fixtures/phpstan/component-names-'.$name.'.php';
    }
}
