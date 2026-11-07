<?php

declare(strict_types=1);

namespace Phil\DevTools\Testing;

use Symfony\Bridge\PhpUnit\HttpRecorder;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Recorder\Redactor\DefaultRedactor;
use Symfony\Component\HttpClient\RecorderHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The HTTP recordings of an integration test, on the recorder of Symfony 8.2: a test marked `#[UseRecord]` replays its
 * exchanges from a HAR file committed beside it, and records them again against the real service when
 * `SYMFONY_HTTP_RECORDER` says so.
 *
 * ```php
 * #[UseRecord]
 * public function testItConverts(): void
 * {
 *     $gotenberg = new Gotenberg(
 *         HttpRecording::client(),
 *         HttpRecording::serviceUrl(TestServices::GOTENBERG, 'http://127.0.0.1:3011'),
 *     );
 * }
 * ```
 *
 * It needs `symfony/http-client` and `symfony/phpunit-bridge` 8.2 or later, and the `SymfonyExtension` of the bridge in
 * the PHPUnit configuration, which `phil-dev init` adds when the bridge is installed.
 */
final class HttpRecording
{
    public const string MODE_VARIABLE = 'SYMFONY_HTTP_RECORDER';

    /**
     * The mode the recorder of the bridge runs in: "replay" by default, or "record", "missing" or "passthrough".
     */
    public static function mode(): string
    {
        $mode = getenv(self::MODE_VARIABLE);

        return \is_string($mode) && '' !== $mode ? $mode : 'replay';
    }

    /**
     * Whether the test reaches no network: the requests are answered from the HAR files.
     */
    public static function replays(): bool
    {
        return 'replay' === self::mode();
    }

    /**
     * The URL of a service: while replaying, the one the recordings were made against, since a request is matched on its
     * URL; otherwise the variable's, or that same URL, where `phil-dev services up` publishes the service.
     */
    public static function serviceUrl(string $variable, string $recorded): string
    {
        if (self::replays()) {
            return $recorded;
        }

        return ServiceUrl::first($variable) ?? $recorded;
    }

    /**
     * An HTTP client that records into and replays from the HAR file of the running test, masking the built-in
     * secrets of Symfony's redactor and the names given — the query parameter of a licence key, the header of a
     * vendor's token.
     *
     * @param list<string> $redact header, query-string, form and JSON field names to mask besides the built-in ones
     */
    public static function client(array $redact = [], ?HttpClientInterface $inner = null): HttpClientInterface
    {
        return new RecorderHttpClient($inner ?? HttpClient::create(), new HttpRecorder(), new DefaultRedactor($redact));
    }
}
