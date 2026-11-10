<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

use Other\Attribute\AsLiveComponent as NotLive;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent as Aliased;

#[AsTwigComponent]
final class Alert
{
}

#[AsLiveComponent(name: 'counter')]
final class Counter
{
}

#[NotLive, AsLiveComponent]
#[AsTwigComponent]
final class SecondInItsGroup
{
}

#[Aliased]
final class ThroughAnAlias
{
}

#[\symfony\ux\twigcomponent\attribute\astwigcomponent]
final class WrittenInLowerCase
{
}

#[AsTwigComponent]
final class Lowercasecomponent
{
}
