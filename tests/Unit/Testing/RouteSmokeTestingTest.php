<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Testing;

use Ergebnis\PHPUnit\SlowTestDetector\Attribute\MaximumDuration;
use LogicException;
use Override;
use Phil\DevTools\Testing\RouteSmokeTesting;
use Phil\DevTools\Testing\SmokeRoutes;
use Phil\DevTools\Tests\Unit\Testing\RouteSmoke\EmptyKernel;
use Phil\DevTools\Tests\Unit\Testing\RouteSmoke\SmokeKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The trait run as a bundle runs it, on the routes of a small application: the routes it keeps answer under 500, and
 * the provider lists exactly them.
 */
#[CoversTrait(RouteSmokeTesting::class)]
#[CoversClass(SmokeRoutes::class)]
final class RouteSmokeTestingTest extends WebTestCase
{
    use RouteSmokeTesting;

    #[Override]
    protected static function getKernelClass(): string
    {
        return SmokeKernel::class;
    }

    /**
     * @return list<array<string, string>>
     */
    protected static function smokeRouteParameters(): array
    {
        return [['_locale' => 'fr'], ['_locale' => 'en'], ['_locale' => 'de']];
    }

    #[MaximumDuration(2000)]
    public function testTheProviderListsEveryGetRouteItCanRequest(): void
    {
        self::assertSame([
            'GET /' => ['routeName' => 'home', 'path' => '/'],
            'GET /any' => ['routeName' => 'any_method', 'path' => '/any'],
            'GET /gone' => ['routeName' => 'gone', 'path' => '/gone'],
            'GET /list' => ['routeName' => 'paged', 'path' => '/list'],
            'GET /fr/hello' => ['routeName' => 'hello', 'path' => '/fr/hello'],
            'GET /en/hello' => ['routeName' => 'hello', 'path' => '/en/hello'],
        ], self::provideSmokeRoutes());
        self::assertFalse(self::$booted, 'The provider shuts the kernel down, so the test can create its client.');
    }

    #[MaximumDuration(2000)]
    public function testAServerErrorFailsTheTest(): void
    {
        try {
            $this->testRouteAnswersWithoutServerError('boom', '/internal');
        } catch (ExpectationFailedException $expectationFailedException) {
            self::assertStringContainsString('GET /internal, of the route "boom", answered 500.', $expectationFailedException->getMessage());

            return;
        }

        self::fail('A route that answers 500 fails the smoke test.');
    }

    public function testTheDefaultsLeaveOutTheInternalRoutesAndTryNoParameter(): void
    {
        $default = new class('default') extends WebTestCase {
            use RouteSmokeTesting;

            /**
             * @return array{list<array<string, string>>, list<string>}
             */
            public static function defaults(): array
            {
                return [
                    self::smokeRouteParameters(),
                    self::smokeRouteExclusions(),
                ];
            }
        };

        self::assertSame([[], ['_']], $default::defaults());
    }

    #[MaximumDuration(2000)]
    public function testAKernelWithNothingToRequestIsAnError(): void
    {
        $_SERVER['KERNEL_CLASS'] = EmptyKernel::class;
        $empty = new class('empty') extends WebTestCase {
            use RouteSmokeTesting;
        };

        try {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessageMatches('/^The test kernel of .+ has no GET route without a parameter to request\.$/');
            $empty::provideSmokeRoutes();
        } finally {
            unset($_SERVER['KERNEL_CLASS']);
            self::assertFalse(self::$booted, 'The kernel is shut down even when nothing is found.');
        }
    }
}
