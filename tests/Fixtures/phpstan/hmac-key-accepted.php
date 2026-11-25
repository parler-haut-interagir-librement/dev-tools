<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

final class AcceptedSigner
{
    private string $key;

    public function __construct(string $secret, private string $totpSeed, private string $signingKey)
    {
        $this->key = hash_hkdf('sha256', $secret, 32, 'phil_example.digest');
    }

    /**
     * @param array<string, string> $config
     */
    public function sign(string $data, array $config, string $name): string
    {
        $context = hash_init('sha256');
        hash_update($context, $data);

        return hash_hmac('sha256', $data, $this->key)
            .hash_hmac('sha1', $data, $this->totpSeed)
            .hash_hmac('sha256', $data, $this->signingKey)
            .hash_hmac('sha256', $data, hash_hkdf('sha256', $config['secret'], 32, 'phil_example.other'))
            .hash_hmac('sha256', $data, $config['key'])
            .hash_hmac('sha256', $data, $config[$name])
            .hash_hmac('sha256', $data, 'kernel secret')
            .hash_hmac('sha256', $data, $this->key, true)
            .hash_hmac(...['sha256', $data, $this->key])
            .sodium_crypto_generichash($data)
            .hash_final($context)
            .hash_hmac(algo: 'sha256', data: $data, key: $this->key);
    }

    public function callable(): \Closure
    {
        return hash_hmac(...);
    }
}
