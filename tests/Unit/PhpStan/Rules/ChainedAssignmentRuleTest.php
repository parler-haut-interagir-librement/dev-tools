<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\PhpStan\Rules;

use Override;
use Phil\DevTools\DevTools;
use Phil\DevTools\PhpStan\Rules\ChainedAssignmentRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @extends RuleTestCase<ChainedAssignmentRule>
 */
#[CoversClass(ChainedAssignmentRule::class)]
final class ChainedAssignmentRuleTest extends RuleTestCase
{
    private const string NEW = 'A chained assignment gives one new object to each of its targets: assign each target its own new, or assign the variable to the others on lines of their own.';

    private const string NEW_TIP = 'Every target holds the same object, and a change through one is seen through the others.';

    private const string HOOK = 'A chained assignment passes on the value written to %sHookedOrder::$total, not the value its set hook stored: write the property, then read it on a line of its own.';

    private const string FIXTURES = 'Phil\\DevTools\\Tests\\Fixtures\\PhpStan\\';

    private const string HOOK_TIP = 'An assignment is worth its right-hand side: the set hook of the property never reaches the other targets.';

    public function testAccepted(): void
    {
        $this->analyse([$this->fixture('accepted')], []);
    }

    public function testRefused(): void
    {
        $this->analyse([$this->fixture('refused')], [
            [self::NEW, 16, self::NEW_TIP],
            [self::NEW, 17, self::NEW_TIP],
            [\sprintf(self::HOOK, self::FIXTURES), 18, self::HOOK_TIP],
            [\sprintf(self::HOOK, self::FIXTURES), 19, self::HOOK_TIP],
            [\sprintf(self::HOOK, self::FIXTURES), 20, self::HOOK_TIP],
            [self::NEW, 21, self::NEW_TIP],
            [\sprintf(self::HOOK, self::FIXTURES), 22, self::HOOK_TIP],
            [\sprintf(self::HOOK, self::FIXTURES), 23, self::HOOK_TIP],
        ]);
    }

    public function testTheSharedConfigurationLoadsTheRule(): void
    {
        self::assertStringContainsString('- '.ChainedAssignmentRule::class, (string) file_get_contents(DevTools::directory().'/config/phpstan.neon'));
        self::assertStringContainsString('- '.ChainedAssignmentRule::class, (string) file_get_contents(DevTools::directory().'/config/phpstan-witnesses.neon'));
    }

    public function testTheIdentifierIsStable(): void
    {
        self::assertSame('phil.chainedAssignment', ChainedAssignmentRule::IDENTIFIER);
    }

    #[Override]
    protected function getRule(): Rule
    {
        return new ChainedAssignmentRule();
    }

    /**
     * The fixture, declared too, so that PHPStan finds its classes and their parents by reflection.
     */
    private function fixture(string $name): string
    {
        $file = DevTools::directory().'/tests/Fixtures/phpstan/chained-assignment-'.$name.'.php';
        require_once $file;

        return $file;
    }
}
