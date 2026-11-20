<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;

/**
 * @param array<string, int|string> $values
 */
function acceptedLogging(LoggerInterface $logger, ?LoggerInterface $optional, object $other, int $order, RuntimeException $exception, array $values, string $name): void
{
    $prefix = 'Order';
    $logger->info('Order {order} shipped.', ['order' => $order]);
    $logger->error('Order {order} failed.', ['order' => $order, 'exception' => $exception]);
    $logger->log(LogLevel::WARNING, 'Order {order} late.', ['order' => $order, 'values' => $values]);
    $logger->debug('A message long enough to be split '.'over two literals.');
    $logger->notice($prefix.' {order} paid.', ['order' => $order]);
    $logger->alert(context: ['order' => $order], message: 'Order {order} lost.');
    $optional?->critical('Order {order} refunded.', ['order' => $order]);
    $logger->emergency($name);
    $logger->info(...['Order {order} sent.', ['order' => $exception]]);
    $other->info("Not a logger {$order}", ['exception' => new \stdClass()]);
    $logger->info(...);
    $logger->{$name}("Dynamic {$order}");
    $logger->info('Order {order} has no context.');
    $logger->log(LogLevel::INFO, message: 'Order {order} kept.', context: ['order' => $order]);
    $logger->debug('Order {order}, too deep to be read.', ['a' => ['b' => ['c' => ['d' => ['e' => new \stdClass()]]]]]);
    $logger->info(($order > 0 ? 'Order ' : 'Refund ').'{order} closed.', ['order' => $order]);
    $logger->isHandling("Not a method of PSR-3 {$order}");
}
