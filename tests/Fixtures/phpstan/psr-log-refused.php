<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use RuntimeException;
use Stringable;

/**
 * @param list<DateTimeImmutable> $dates
 */
function refusedLogging(LoggerInterface $logger, ?LoggerInterface $optional, NullLogger $concrete, int $order, string $name, DateTimeImmutable $date, Stringable|RuntimeException $either, array $dates, object $anything): void
{
    $logger->info("Order {$order} shipped.");
    $logger->error('Order '.$order.' failed.');
    $logger->warning(\sprintf('Order %d late.', $order));
    $logger->log(LogLevel::NOTICE, vsprintf('Order %d paid.', [$order]));
    $logger->debug('Order {order}.', ['date' => $date]);
    $logger->debug('Order {order}.', ['order' => ['placed' => $date]]);
    $logger->debug('Order {order}.', ['either' => $either]);
    $logger->debug('Order {order}.', ['dates' => $dates]);
    $logger->debug('Order {order}.', [$anything]);
    $optional?->critical('Order '.$name);
    $concrete->alert('Order {order}.', context: ['date' => $date]);
    $logger->LOG(LogLevel::INFO, "Order {$name}", ['date' => $date]);
    $logger->log(LogLevel::INFO, context: ['date' => $date], message: 'Order {order}.');
    $optional->warning("Order {$name}");
    $logger->debug('Order {order}.', ['a' => ['b' => ['c' => ['d' => $date]]]]);
}
