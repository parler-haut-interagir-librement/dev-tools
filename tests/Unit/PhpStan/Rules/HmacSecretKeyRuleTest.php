<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\PhpStan\Rules;

use Override;
use Phil\DevTools\DevTools;
use Phil\DevTools\PhpStan\Rules\HmacSecretKeyRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @extends RuleTestCase<HmacSecretKeyRule>
 */
#[CoversClass(HmacSecretKeyRule::class)]
final class HmacSecretKeyRuleTest extends RuleTestCase
{
    private const string TIP = 'One key per use, under a label of its own: a digest or a signature made for one use never matches one made for another.';

    public function testAccepted(): void
    {
        $this->analyse([$this->fixture('accepted')], []);
    }

    public function testRefused(): void
    {
        $this->analyse([$this->fixture('refused')], [
            [$this->message('hash_hmac', '->secret'), 22, self::TIP],
            [$this->message('hash_hmac', '$kernelSecret'), 23, self::TIP],
            [$this->message('hash_hmac', "['secret']"), 24, self::TIP],
            [$this->message('hash_hmac', "'%kernel.secret%'"), 25, self::TIP],
            [$this->message('hash_hmac', "getParameter('kernel.secret')"), 26, self::TIP],
            [$this->message('hash_hmac', '::$appSecret'), 27, self::TIP],
            [$this->message('hash_hmac_file', '->secret'), 28, self::TIP],
            [$this->message('hash_init', '->secret'), 29, self::TIP],
            [$this->message('sodium_crypto_auth', '->secret'), 30, self::TIP],
            [$this->message('sodium_crypto_auth_verify', '->secret'), 31, self::TIP],
            [$this->message('sodium_crypto_generichash', '->secret'), 32, self::TIP],
            [$this->message('hash_hmac', '->secret'), 33, self::TIP],
            [$this->message('hash_hmac', '->secret'), 34, self::TIP],
            [$this->message('hash_hmac', "['kernel.secret']"), 35, self::TIP],
            [$this->message('hash_hmac', '->secret'), 40, self::TIP],
        ]);
    }

    public function testTheSharedConfigurationLoadsTheRule(): void
    {
        self::assertStringContainsString('- '.HmacSecretKeyRule::class, (string) file_get_contents(DevTools::directory().'/config/phpstan.neon'));
        self::assertStringContainsString('- '.HmacSecretKeyRule::class, (string) file_get_contents(DevTools::directory().'/config/phpstan-witnesses.neon'));
    }

    public function testTheIdentifierIsStable(): void
    {
        self::assertSame('phil.hmacSecretKey', HmacSecretKeyRule::IDENTIFIER);
    }

    private function message(string $function, string $held): string
    {
        return 'The key of '.$function.'() is the application secret as given ('.$held.'): derive a key for this use, such as hash_hkdf(\'sha256\', $secret, 32, \'phil_example.digest\').';
    }

    #[Override]
    protected function getRule(): Rule
    {
        return new HmacSecretKeyRule();
    }

    private function fixture(string $name): string
    {
        return DevTools::directory().'/tests/Fixtures/phpstan/hmac-key-'.$name.'.php';
    }
}
