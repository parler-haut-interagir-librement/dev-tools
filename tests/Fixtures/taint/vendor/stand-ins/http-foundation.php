<?php

namespace Symfony\Component\HttpFoundation;

class ParameterBag
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }
}

class ServerBag extends ParameterBag
{
}

class HeaderBag implements \IteratorAggregate, \Countable, \Stringable
{
    public function get(string $key, ?string $default = null): ?string
    {
        return $default;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator([]);
    }

    public function count(): int
    {
        return 0;
    }

    public function __toString(): string
    {
        return '';
    }
}

class Request
{
    public ServerBag $server;

    public HeaderBag $headers;

    public function __construct()
    {
        $this->server = new ServerBag();
        $this->headers = new HeaderBag();
    }

    public function getHost(): string
    {
        return '';
    }

    public function getSchemeAndHttpHost(): string
    {
        return '';
    }

    public function getUri(): string
    {
        return '';
    }

    public function getPathInfo(): string
    {
        return '';
    }
}
