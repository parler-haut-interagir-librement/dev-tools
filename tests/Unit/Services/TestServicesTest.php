<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Services;

use Phil\DevTools\Services\TestServices;
use Phil\DevTools\Testing\ServiceUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TestServices::class)]
#[CoversClass(ServiceUrl::class)]
final class TestServicesTest extends TestCase
{
    public function testTheDefaults(): void
    {
        self::assertSame([
            'PHIL_TEST_MYSQL_URL' => 'mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4',
            'PHIL_TEST_POSTGRESQL_URL' => 'postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17',
            'PHIL_GOTENBERG_URL' => 'http://127.0.0.1:3011',
        ], TestServices::environment([]));
    }

    public function testThePortsAndTheValuesAlreadySet(): void
    {
        self::assertSame([
            'PHIL_TEST_MYSQL_URL' => 'mysql://phil:phil@127.0.0.1:3307/phil_test?serverVersion=8.4',
            'PHIL_TEST_POSTGRESQL_URL' => 'postgresql://elsewhere/db',
            'PHIL_GOTENBERG_URL' => 'http://127.0.0.1:3012',
        ], TestServices::environment([
            'PHIL_DEV_MYSQL_PORT' => '3307',
            'PHIL_DEV_GOTENBERG_PORT' => '3012',
            'PHIL_TEST_POSTGRESQL_URL' => 'postgresql://elsewhere/db',
            'PHIL_TEST_MYSQL_URL' => '',
        ]));
    }

    public function testTheComposeFileReservesNoMercurePort(): void
    {
        $compose = (string) file_get_contents(TestServices::composeFile());

        self::assertStringContainsString('${PHIL_DEV_GOTENBERG_PORT:-3011}:3000', $compose);
        self::assertStringContainsString('image: mysql:8.4', $compose);
        self::assertStringContainsString('image: postgres:17-alpine', $compose);
        self::assertStringContainsString('image: gotenberg/gotenberg:9', $compose);
        self::assertStringNotContainsString(':3999', $compose);
    }

    public function testTheEnvironmentByDefault(): void
    {
        self::assertCount(3, TestServices::environment());
    }

    public function testServiceUrl(): void
    {
        putenv('PHIL_DEV_TEST_A=');
        putenv('PHIL_DEV_TEST_B=b');

        try {
            self::assertSame('b', ServiceUrl::first('PHIL_DEV_TEST_NONE', 'PHIL_DEV_TEST_A', 'PHIL_DEV_TEST_B'));
            self::assertNull(ServiceUrl::first('PHIL_DEV_TEST_NONE', 'PHIL_DEV_TEST_A'));
        } finally {
            putenv('PHIL_DEV_TEST_A');
            putenv('PHIL_DEV_TEST_B');
        }
    }
}
