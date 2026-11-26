<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Testing\RouteSmoke;

use Override;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application whose only route takes a parameter: no request to make.
 */
final class EmptyKernel extends RouteSmokeKernel
{
    #[Override]
    protected function routes(RoutingConfigurator $routes): void
    {
        $routes->add('post', '/post/{slug}')->controller('kernel::page');
    }
}
