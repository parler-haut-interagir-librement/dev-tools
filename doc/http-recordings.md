# HTTP recordings

An integration test that calls an HTTP service replays the exchanges recorded in a HAR file, offline, instead of
being skipped when the service is absent. It uses the recorder of Symfony 8.2: `RecorderHttpClient` in
`symfony/http-client`, and the `#[UseRecord]` attribute with the `SymfonyExtension` of `symfony/phpunit-bridge`.

## Writing the test

```php
use Phil\DevTools\Services\TestServices;
use Phil\DevTools\Testing\HttpRecording;
use Symfony\Bridge\PhpUnit\Attribute\UseRecord;

#[UseRecord]
public function testItConvertsAPage(): void
{
    $gotenberg = new Gotenberg(
        HttpRecording::client(),
        HttpRecording::serviceUrl(TestServices::GOTENBERG, 'http://127.0.0.1:3011'),
    );

    // …
}
```

`HttpRecording::client()` wraps the client in a `RecorderHttpClient` driven by the attribute. While replaying,
`serviceUrl()` returns the URL the recordings were made against, since a request is matched on its method, its URL and
its body; while recording, the variable's URL, or the same one where `phil-dev services up` publishes the service.

In a kernel test, `framework.http_client.recorder` is enabled with `framework.test`, and every client of the container
records and replays the same way.

## Where the files live

Beside the test, in each package: `tests/Integration/GotenbergTest.php` records into
`tests/Integration/GotenbergTest/testItConvertsAPage.har`, or `testItConvertsAPage@dataSetName.har` with a data
provider; `#[UseRecord('name.har')]` names a file of its own. The HAR files are committed with the test, so that the
package checked on its own replays them too.

## Recording and replaying

`SYMFONY_HTTP_RECORDER` chooses the mode:

| Value | What happens |
|---|---|
| unset, or `replay` | The requests are answered from the HAR file; a request it does not hold fails the test. |
| `record` | The requests reach the service, and the HAR files of the tests that run are rewritten. |
| `missing` | The file answers what it holds, and the requests it does not hold are made and appended. |
| `passthrough` | The requests reach the service, and no file is touched. |

The Docker services are the recording path:

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services record -- --filter=Gotenberg
```

`services record` runs the Integration suite with the services' variables and `SYMFONY_HTTP_RECORDER=record`. A service
that is not a container — a model provider, a geolocation download — is recorded with its own credentials in the
environment, once, by whoever holds them.

## Secrets

The recorder masks secrets before writing: the `Authorization`, `Proxy-Authorization`, `Cookie`, `Set-Cookie`,
`X-Api-Key`, `X-Auth-Token` and `DPoP` headers, and the query, form and JSON fields named `token`, `access_token`,
`api_key`, `apikey`, `secret`, `password`, `key`, `client_secret`, `code`, `refresh_token`, `id_token` and the like, are
written `[REDACTED]`, in the request and in the response. A request is matched with its secrets masked, so a
recording replays with any credentials.

A package adds the names of its own secrets: `HttpRecording::client(['license_key'])` for a licence key in a query
string, `['x-goog-api-key']` for a vendor's header; in a kernel test, `framework.http_client.recorder.redact`. Read a
new recording before committing it.

## Limits

- Only fully received responses are recorded; an error response is recorded when it is read with `getContent(false)`
  or `toArray(false)`.
- A large download — a geolocation database — makes a large HAR file: record a small test database instead.
- The databases are not HTTP: their tests still need the MySQL and PostgreSQL containers.
