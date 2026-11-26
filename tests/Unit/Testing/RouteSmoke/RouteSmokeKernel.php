<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Testing\RouteSmoke;

use Override;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * The applications of the route smoke tests, each with its own routes and a cache of its own in the temporary
 * directory; their controllers are methods of the kernel.
 */
abstract class RouteSmokeKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct()
    {
        parent::__construct('test', false);
    }

    /**
     * @return iterable<FrameworkBundle>
     */
    #[Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-dev-route-smoke/'.hash('xxh128', static::class).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-dev-route-smoke/'.hash('xxh128', static::class).'/log';
    }

    public function page(): Response
    {
        return new Response('page');
    }

    public function missing(): Response
    {
        return new Response('missing', Response::HTTP_NOT_FOUND);
    }

    public function fail(): never
    {
        throw new RuntimeException('The route fails on purpose.');
    }

    abstract protected function routes(RoutingConfigurator $routes): void;

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'route-smoke-test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => false],
            'router' => ['utf8' => true],
        ]);
        $container->services()->set('logger', NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $this->routes($routes);
    }
}
