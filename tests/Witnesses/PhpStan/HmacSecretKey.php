<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Witnesses\PhpStan;

/**
 * The application secret is never an HMAC key as it is.
 *
 * Expected identifier: `phil.hmacSecretKey`
 */
final class HmacSecretKey
{
    public function __construct(private string $secret)
    {
    }

    public function digest(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secret);
    }
}
