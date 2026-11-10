<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

use Other\Attribute\AsLiveComponent as NotLive;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent as Aliased;

#[AsTwigComponent]
final class AlertComponent
{
}

#[AsLiveComponent]
final class CounterComponent
{
}

#[AsTwigComponent('component')]
final class Component
{
}

final class PlainWithoutAttribute
{
}

#[NotLive]
final class SameShortNameElsewhere
{
}

$anonymous = new #[AsTwigComponent] class {
};
