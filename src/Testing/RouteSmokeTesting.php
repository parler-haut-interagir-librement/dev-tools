<?php

declare(strict_types=1);

namespace Phil\DevTools\Testing;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * A route smoke test for the test kernel of a bundle or an application: every GET route without a parameter answers
 * without a server error (a status under 500).
 *
 * ```php
 * final class RouteSmokeTest extends WebTestCase
 * {
 *     use RouteSmokeTesting;
 *
 *     protected static function getKernelClass(): string
 *     {
 *         return TestKernel::class;
 *     }
 * }
 * ```
 *
 * A route whose parameters have no default is requested once per set of `smokeRouteParameters()` that supplies them
 * all, and the routes whose name starts with a prefix of `smokeRouteExclusions()` are left out: by default, those of
 * the profiler and the error pages, named `_…`. It needs `symfony/framework-bundle` and `symfony/browser-kit`.
 *
 * @phpstan-require-extends WebTestCase
 */
trait RouteSmokeTesting
{
    #[DataProvider('provideSmokeRoutes')]
    public function testRouteAnswersWithoutServerError(string $routeName, string $path): void
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->request('GET', $path);

        $status = $client->getResponse()->getStatusCode();

        self::assertLessThan(500, $status, \sprintf('GET %s, of the route "%s", answered %d.', $path, $routeName, $status));
    }

    /**
     * @return array<string, array{routeName: string, path: string}>
     */
    public static function provideSmokeRoutes(): array
    {
        self::bootKernel();

        try {
            $router = self::getContainer()->get('router');

            if (!$router instanceof RouterInterface) {
                throw new LogicException('The "router" service of the test kernel is not a router.');
            }

            $requests = SmokeRoutes::from($router->getRouteCollection(), $router, static::smokeRouteParameters(), static::smokeRouteExclusions());
        } finally {
            self::ensureKernelShutdown();
        }

        if ([] === $requests) {
            throw new LogicException(\sprintf('The test kernel of %s has no GET route without a parameter to request.', static::class));
        }

        return $requests;
    }

    /**
     * The values tried for the parameters a route has no default for, one request per set that supplies them all, such
     * as `[['_locale' => 'fr'], ['_locale' => 'en']]`.
     *
     * @return list<array<string, string>>
     */
    protected static function smokeRouteParameters(): array
    {
        return [];
    }

    /**
     * The prefixes of the route names left out.
     *
     * @return list<string>
     */
    protected static function smokeRouteExclusions(): array
    {
        return ['_'];
    }
}
