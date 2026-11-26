<?php

declare(strict_types=1);

namespace Phil\DevTools\Testing;

use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The requests of a route smoke test: one GET per route that answers GET and needs no parameter, or whose parameters a
 * given set supplies, such as `['_locale' => 'fr']`.
 *
 * It needs `symfony/routing`, which every application that has routes requires.
 */
final readonly class SmokeRoutes
{
    /**
     * @param list<array<string, string>> $parameterSets the values tried for the parameters a route has no default for,
     *                                                   one request per set that supplies them all
     * @param list<string>                $excluded      the prefixes of the route names left out
     *
     * @return array<string, array{routeName: string, path: string}> the requests, keyed "GET <path>", each path once
     */
    public static function from(RouteCollection $routes, UrlGeneratorInterface $generator, array $parameterSets = [], array $excluded = ['_']): array
    {
        $requests = [];

        foreach ($routes as $name => $route) {
            if ([] !== $route->getMethods() && !\in_array('GET', $route->getMethods(), true)) {
                continue;
            }

            if (array_any($excluded, static fn (string $prefix): bool => '' !== $prefix && str_starts_with($name, $prefix))) {
                continue;
            }

            $variables = array_values(array_filter($route->compile()->getVariables(), is_string(...)));
            $missing = array_values(array_diff($variables, array_keys($route->getDefaults())));
            $sets = [] === $missing
                ? [[]]
                : array_filter($parameterSets, static fn (array $set): bool => [] === array_diff($missing, array_keys($set)));

            foreach ($sets as $set) {
                $parameters = array_intersect_key($set, array_flip($variables));

                if (!self::meets($route, $parameters)) {
                    continue;
                }

                try {
                    $path = $generator->generate($name, $parameters);
                } catch (ExceptionInterface) {
                    // A default the requirements of the route refuse, under a strict generator.
                    continue;
                }

                $requests['GET '.$path] ??= ['routeName' => $name, 'path' => $path];
            }
        }

        return $requests;
    }

    /**
     * Whether each value meets the requirement of its parameter, whatever the strictness of the generator: a router
     * that does not check them would build a URL the route never matches.
     *
     * @param array<string, string> $parameters
     */
    private static function meets(Route $route, array $parameters): bool
    {
        $modifiers = true === $route->getOption('utf8') ? 'Du' : 'D';

        foreach ($parameters as $parameter => $value) {
            $requirement = $route->getRequirement($parameter);

            if (null !== $requirement && 1 !== preg_match('{^(?:'.$requirement.')$}'.$modifiers, $value)) {
                return false;
            }
        }

        return true;
    }
}
