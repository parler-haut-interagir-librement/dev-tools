<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A Twig or Live Component has a name that ends with `Component`.
 *
 * Expected identifier: `phil.componentClassName`
 */
#[AsTwigComponent]
final class Alert
{
}
