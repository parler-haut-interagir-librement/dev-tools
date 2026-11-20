<?php

namespace Symfony\Component\Routing;

interface RequestContextAwareInterface
{
}

namespace Symfony\Component\Routing\Generator;

interface UrlGeneratorInterface extends \Symfony\Component\Routing\RequestContextAwareInterface
{
    public const ABSOLUTE_URL = 0;
    public const ABSOLUTE_PATH = 1;
    public const RELATIVE_PATH = 2;
    public const NETWORK_PATH = 3;

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string;
}
