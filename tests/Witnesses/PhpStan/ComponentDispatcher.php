<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The event dispatcher is typed by the interface of the contracts, never by the component's.
 *
 * Expected identifier: `phil.componentDispatcher`
 */
function componentDispatcherWitness(EventDispatcherInterface $dispatcher): object
{
    return $dispatcher->dispatch(new stdClass());
}
