<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Testing\RouteSmoke;

use Override;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * A route of each kind the selection keeps or leaves out, and a route that fails, requested only by the test that
 * expects the failure.
 */
final class SmokeKernel extends RouteSmokeKernel
{
    #[Override]
    protected function routes(RoutingConfigurator $routes): void
    {
        $routes->add('home', '/')->controller('kernel::page')->methods(['GET']);
        $routes->add('any_method', '/any')->controller('kernel::page');
        $routes->add('gone', '/gone')->controller('kernel::missing')->methods(['GET', 'HEAD']);
        $routes->add('submit', '/submit')->controller('kernel::page')->methods(['POST']);
        $routes->add('post', '/post/{slug}')->controller('kernel::page');
        $routes->add('paged', '/list/{page}')->controller('kernel::page')->defaults(['page' => '1']);
        $routes->add('hello', '/{_locale}/hello')->controller('kernel::page')->requirements(['_locale' => 'fr|en']);
        $routes->add('_internal', '/internal')->controller('kernel::fail');
        $routes->add('boom', '/boom')->controller('kernel::fail')->methods(['DELETE']);
    }
}
