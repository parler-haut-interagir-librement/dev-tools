<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

use Psr\Log\LoggerInterface;

/**
 * The message of a PSR-3 logger call is a constant with placeholders.
 *
 * Expected identifier: `phil.psrLogMessage`
 */
final class LogMessage
{
    public function shipped(LoggerInterface $logger, int $order): void
    {
        $logger->info("Order {$order} shipped.");
    }
}
