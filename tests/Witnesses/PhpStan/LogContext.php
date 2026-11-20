<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;

/**
 * The context of a PSR-3 logger call holds no object other than a Throwable.
 *
 * Expected identifier: `phil.psrLogContext`
 */
final class LogContext
{
    public function shipped(LoggerInterface $logger, DateTimeImmutable $date): void
    {
        $logger->info('Order shipped on {date}.', ['date' => $date]);
    }
}
