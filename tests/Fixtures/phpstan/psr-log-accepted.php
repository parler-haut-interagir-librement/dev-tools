<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;

/**
 * @param array<string, int|string> $values
 * @param list<list<list<list<list<\DateTimeImmutable>>>>> $five
 */
function acceptedLogging(LoggerInterface $logger, ?LoggerInterface $optional, object $other, int $order, RuntimeException $exception, array $values, string $name, array $five): void
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
    $logger->info('Order {order} unpacked.', ...[new \stdClass()]);
    $info = 'info';
    $logger->{$info}("Dynamic {$order}");
    $logger->debug('Order {order}, five lists deep.', ['order' => $five]);
    $logger->info(\SPRINTF);
    $logger->isHandling("Not a method of PSR-3 {$order}");
}
