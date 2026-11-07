<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Testing;

use Override;
use Phil\DevTools\Testing\HttpRecording;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\HttpRecorder;
use Symfony\Component\HttpClient\Exception\HarEntryNotFoundException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Recorder\RecorderMode;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(HttpRecording::class)]
final class HttpRecordingTest extends TestCase
{
    use TemporaryDirectory;

    #[Override]
    protected function tearDown(): void
    {
        HttpRecorder::reset();
        putenv('SYMFONY_HTTP_RECORDER');
        putenv('PHIL_DEV_TEST_SERVICE');
    }

    public function testItRecordsWithSecretsMaskedThenReplaysOffline(): void
    {
        $har = $this->temporaryDirectory().'/RecordingTest/testIt.har';
        $service = new MockHttpClient(new MockResponse('{"city": "Montréal", "license_key": "body-secret"}', ['response_headers' => ['content-type' => 'application/json']]));

        HttpRecorder::configure(RecorderMode::Record, $har);
        $recorded = HttpRecording::client(['license_key'], $service)->request('GET', 'http://127.0.0.1:3011/city?license_key=url-secret&ip=1.2.3.4', ['headers' => ['Authorization' => 'Bearer header-secret']]);
        self::assertSame('Montréal', $recorded->toArray()['city']);

        $file = (string) file_get_contents($har);
        self::assertStringNotContainsString('url-secret', $file);
        self::assertStringNotContainsString('body-secret', $file);
        self::assertStringNotContainsString('header-secret', $file);
        self::assertStringContainsString('[REDACTED]', $file);

        HttpRecorder::configure(RecorderMode::Replay, $har);
        $offline = new MockHttpClient(static fn (): never => self::fail('The replay reached the network.'));
        $replayed = HttpRecording::client(['license_key'], $offline)->request('GET', 'http://127.0.0.1:3011/city?license_key=another-secret&ip=1.2.3.4');

        self::assertSame('Montréal', $replayed->toArray()['city']);
    }

    public function testAMissingRecordingFailsWhileReplaying(): void
    {
        HttpRecorder::configure(RecorderMode::Replay, $this->temporaryDirectory().'/none.har');

        $this->expectException(HarEntryNotFoundException::class);

        HttpRecording::client()->request('GET', 'http://127.0.0.1:3011/health')->getContent();
    }

    public function testTheModeAndTheServiceUrl(): void
    {
        putenv('PHIL_DEV_TEST_SERVICE=http://elsewhere:3000');

        self::assertSame('replay', HttpRecording::mode());
        self::assertTrue(HttpRecording::replays());
        self::assertSame('http://127.0.0.1:3011', HttpRecording::serviceUrl('PHIL_DEV_TEST_SERVICE', 'http://127.0.0.1:3011'));

        putenv('SYMFONY_HTTP_RECORDER=record');

        self::assertSame('record', HttpRecording::mode());
        self::assertFalse(HttpRecording::replays());
        self::assertSame('http://elsewhere:3000', HttpRecording::serviceUrl('PHIL_DEV_TEST_SERVICE', 'http://127.0.0.1:3011'));

        putenv('PHIL_DEV_TEST_SERVICE');

        self::assertSame('http://127.0.0.1:3011', HttpRecording::serviceUrl('PHIL_DEV_TEST_SERVICE', 'http://127.0.0.1:3011'));
    }
}
