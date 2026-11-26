<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Testing;

use Phil\DevTools\Testing\SmokeRoutes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(SmokeRoutes::class)]
final class SmokeRoutesTest extends TestCase
{
    public function testItKeepsTheGetRoutesWithoutAMissingParameter(): void
    {
        $routes = $this->routes([
            'home' => new Route('/'),
            'get' => new Route('/get', methods: ['GET']),
            'head_and_get' => new Route('/both', methods: ['HEAD', 'GET']),
            'head' => new Route('/head', methods: ['HEAD']),
            'post' => new Route('/post', methods: ['POST']),
            'slug' => new Route('/article/{slug}'),
            'page' => new Route('/list/{page}', ['page' => 2]),
        ]);

        self::assertSame([
            'GET /' => ['routeName' => 'home', 'path' => '/'],
            'GET /get' => ['routeName' => 'get', 'path' => '/get'],
            'GET /both' => ['routeName' => 'head_and_get', 'path' => '/both'],
            'GET /list' => ['routeName' => 'page', 'path' => '/list'],
        ], SmokeRoutes::from($routes, $this->generator($routes)));
    }

    public function testItLeavesOutTheRoutesWhoseNameStartsWithAnExcludedPrefix(): void
    {
        $routes = $this->routes([
            '_profiler' => new Route('/_profiler'),
            'admin_dashboard' => new Route('/admin'),
            'app_admin' => new Route('/app-admin'),
            'home' => new Route('/'),
        ]);

        self::assertSame(['GET /app-admin', 'GET /'], array_keys(SmokeRoutes::from($routes, $this->generator($routes), [], ['_', 'admin', ''])));
        self::assertSame(['GET /admin', 'GET /app-admin', 'GET /'], array_keys(SmokeRoutes::from($routes, $this->generator($routes))));
        self::assertSame(['GET /_profiler', 'GET /admin', 'GET /app-admin', 'GET /'], array_keys(SmokeRoutes::from($routes, $this->generator($routes), [], [])));
    }

    public function testItTriesEachParameterSetThatSuppliesEveryMissingParameter(): void
    {
        $routes = $this->routes([
            'hello' => new Route('/{_locale}/hello', requirements: ['_locale' => 'fr|en']),
            'article' => new Route('/{_locale}/article/{slug}'),
            'plain' => new Route('/plain'),
        ]);
        $sets = [['_locale' => 'fr', 'theme' => 'dark'], ['_locale' => 'en'], ['_locale' => 'de'], ['slug' => 'only']];

        self::assertSame([
            'GET /fr/hello' => ['routeName' => 'hello', 'path' => '/fr/hello'],
            'GET /en/hello' => ['routeName' => 'hello', 'path' => '/en/hello'],
            'GET /plain' => ['routeName' => 'plain', 'path' => '/plain'],
        ], SmokeRoutes::from($routes, $this->generator($routes), $sets));
    }

    public function testAValueTheRequirementRefusesIsNotTriedEvenByALenientGenerator(): void
    {
        $routes = $this->routes([
            'hello' => new Route('/{_locale}/hello', requirements: ['_locale' => 'fr|en']),
            'letter' => new Route('/letter/{letter}', requirements: ['letter' => '.'], options: ['utf8' => true]),
            'byte' => new Route('/byte/{letter}', requirements: ['letter' => '.']),
        ]);
        $generator = $this->generator($routes);
        $generator->setStrictRequirements(null);

        self::assertSame(
            ['GET /fr/hello', 'GET /letter/%C3%A9'],
            array_keys(SmokeRoutes::from($routes, $generator, [['_locale' => 'fr'], ['_locale' => 'fra'], ['_locale' => 'xfr'], ['letter' => 'é']])),
        );
    }

    public function testADefaultTheRequirementRefusesLeavesTheRouteOut(): void
    {
        $routes = $this->routes([
            'page' => new Route('/list/{page}/items', ['page' => 'first'], ['page' => '\d+']),
            'home' => new Route('/'),
        ]);

        self::assertSame(['GET /'], array_keys(SmokeRoutes::from($routes, $this->generator($routes))));
    }

    public function testAPathIsRequestedOnceAndTheFirstRouteNamesIt(): void
    {
        $routes = $this->routes([
            'first' => new Route('/same'),
            'second' => new Route('/same'),
        ]);

        self::assertSame(['GET /same' => ['routeName' => 'first', 'path' => '/same']], SmokeRoutes::from($routes, $this->generator($routes)));
    }

    /**
     * @param array<string, Route> $routes
     */
    private function routes(array $routes): RouteCollection
    {
        $collection = new RouteCollection();

        foreach ($routes as $name => $route) {
            $collection->add($name, $route);
        }

        return $collection;
    }

    private function generator(RouteCollection $routes): UrlGenerator
    {
        return new UrlGenerator($routes, new RequestContext());
    }
}
