<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Fixtures\PhpStan;

use Symfony\Component\DependencyInjection\ContainerInterface;

final class RefusedSigner
{
    private static string $appSecret = '';

    public function __construct(private string $secret, private ?self $other = null)
    {
    }

    /**
     * @param array<string, string> $config
     */
    public function sign(string $data, array $config, string $kernelSecret, ContainerInterface $container, string $file): string
    {
        return hash_hmac('sha256', $data, $this->secret)
            .hash_hmac('sha256', $data, $kernelSecret, true)
            .hash_hmac('sha256', $data, $config['secret'])
            .hash_hmac('sha256', $data, '%kernel.secret%')
            .hash_hmac('sha256', $data, (string) $container->getParameter('kernel.secret'))
            .hash_hmac('sha256', $data, self::$appSecret)
            .hash_hmac_file('sha256', $file, $this->secret)
            .hash_final(hash_init('sha256', \HASH_HMAC, $this->secret))
            .sodium_crypto_auth($data, $this->secret)
            .(sodium_crypto_auth_verify($data, $data, $this->secret) ? 'y' : 'n')
            .sodium_crypto_generichash($data, $this->secret)
            .hash_hmac(key: $this->secret, algo: 'sha256', data: $data)
            .hash_hmac('sha256', $data, (string) $this->other?->secret)
            .hash_hmac('sha256', $data, $config['kernel.secret']);
    }
}
