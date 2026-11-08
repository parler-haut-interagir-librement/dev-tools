<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Support;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\After;

/**
 * Serves a directory with the built-in server of PHP, standing in for FrankenPHP, until the test ends.
 */
trait BuiltInServer
{
    /** @var list<resource> */
    private array $servers = [];

    protected function serve(string $directory): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        Assert::assertIsResource($socket);
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);

        $process = proc_open([\PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $directory], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes); // @phpstan-ignore disallowed.function
        Assert::assertIsResource($process);
        $this->servers[] = $process;

        for ($i = 0; $i < 100 && false === @fsockopen('127.0.0.1', $port); ++$i) {
            usleep(20_000);
        }

        return $port;
    }

    #[After]
    protected function stopServers(): void
    {
        foreach ($this->servers as $server) {
            proc_terminate($server);
            proc_close($server);
        }

        $this->servers = [];
    }
}
