<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Init;

use Phil\DevTools\Exception\WorkspaceNotFound;
use Phil\DevTools\Init\ComposerJson;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComposerJson::class)]
final class ComposerJsonTest extends TestCase
{
    use TemporaryDirectory;

    public function testItAddsWhatIsMissingAndKeepsWhatDiffers(): void
    {
        $file = $this->temporaryDirectory().'/composer.json';
        $this->put($file, '{"name": "phil/x", "description": "Événements", "require": {}, "extra": "text", "scripts": {"cs": "mine", "same": "x"}}');
        $composer = ComposerJson::read($file);

        $composer->set('config.process-timeout', 0);
        $composer->set('config.process-timeout', 0);
        $composer->set('extra.bamarni-bin', ['a' => true]);
        $composer->set('name', 'phil/other');
        $composer->setIn('scripts', 'cs', 'theirs');
        $composer->setIn('scripts', 'cs:fix', 'fix');
        $composer->setIn('scripts', 'same', 'x');
        $composer->setIn('scripts-descriptions', 'cs', 'Style.');

        self::assertSame(['config.process-timeout', 'extra.bamarni-bin', 'scripts.cs:fix', 'scripts-descriptions.cs'], $composer->changes());
        self::assertSame(['name', 'scripts.cs'], $composer->kept());
        self::assertTrue($composer->save());

        $saved = (string) file_get_contents($file);
        self::assertStringContainsString('"description": "Événements"', $saved);
        self::assertStringContainsString("\"config\": {\n        \"process-timeout\": 0\n    }", $saved);
        self::assertStringContainsString("\"extra\": {\n        \"bamarni-bin\": {\n            \"a\": true", $saved);
        self::assertStringContainsString('"require": {}', $saved);
        self::assertStringContainsString('"cs": "mine"', $saved);
        self::assertStringContainsString('"cs:fix": "fix"', $saved);
        self::assertStringEndsWith("}\n", $saved);
        self::assertFalse(ComposerJson::read($file)->save());
    }

    public function testForcingReplaces(): void
    {
        $file = $this->temporaryDirectory().'/composer.json';
        $this->put($file, '{"name": "phil/x", "scripts": "text", "scripts-descriptions": {"cs": "Mine."}}');
        $composer = ComposerJson::read($file);

        $composer->set('name', 'phil/y', true);
        $composer->setIn('scripts', 'cs', 'check');
        $composer->setIn('scripts-descriptions', 'cs', 'Theirs.', true);

        self::assertSame(['name (replaced)', 'scripts.cs', 'scripts-descriptions.cs (replaced)'], $composer->changes());
        self::assertSame([], $composer->kept());
        self::assertStringContainsString('"name": "phil/y"', $composer->encode());
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectException(WorkspaceNotFound::class);
        $this->expectExceptionMessageIsOrContains('does not exist.');

        ComposerJson::read($this->temporaryDirectory().'/composer.json');
    }

    public function testInvalidJsonIsRefused(): void
    {
        $file = $this->temporaryDirectory().'/composer.json';
        $this->put($file, '{');

        $this->expectException(WorkspaceNotFound::class);
        $this->expectExceptionMessageIsOrContains('is not valid JSON');

        ComposerJson::read($file);
    }

    public function testAListIsRefused(): void
    {
        $file = $this->temporaryDirectory().'/composer.json';
        $this->put($file, '[1]');

        $this->expectException(WorkspaceNotFound::class);
        $this->expectExceptionMessageIsOrContains('is not a JSON object.');

        ComposerJson::read($file);
    }

    public function testAnEmptyObjectIsAccepted(): void
    {
        $file = $this->temporaryDirectory().'/composer.json';
        $this->put($file, '{}');

        self::assertSame("{}\n", ComposerJson::read($file)->encode());
    }
}
