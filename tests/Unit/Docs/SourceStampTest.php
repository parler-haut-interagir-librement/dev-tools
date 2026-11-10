<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Docs;

use Phil\DevTools\Docs\SourceStamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceStamp::class)]
final class SourceStampTest extends TestCase
{
    public function testTheFingerprintIgnoresLineEndings(): void
    {
        self::assertSame(hash('sha256', "a\nb\n"), SourceStamp::fingerprint("a\r\nb\n"));
        self::assertNotSame(SourceStamp::fingerprint('a'), SourceStamp::fingerprint('b'));
    }

    public function testApplyingThenReading(): void
    {
        $stamped = SourceStamp::apply("# Titre\n", 'English');

        self::assertSame('<!-- phil-dev source-sha256='.hash('sha256', 'English')." -->\n# Titre\n", $stamped);
        self::assertSame(hash('sha256', 'English'), SourceStamp::read($stamped));
    }

    public function testApplyingAgainReplacesTheStamp(): void
    {
        $stamped = SourceStamp::apply(SourceStamp::apply("# Titre\n", 'old'), 'new');

        self::assertSame('<!-- phil-dev source-sha256='.hash('sha256', 'new')." -->\n# Titre\n", $stamped);
    }

    public function testACrlfStampIsRead(): void
    {
        $hash = str_repeat('a', 64);

        self::assertSame($hash, SourceStamp::read('<!-- phil-dev source-sha256='.$hash." -->\r\n# T"));
        self::assertSame('<!-- phil-dev source-sha256='.hash('sha256', 'x')." -->\n# T", SourceStamp::apply('<!-- phil-dev source-sha256='.$hash." -->\r\n# T", 'x'));
    }

    public function testWhatIsNotAStamp(): void
    {
        $hash = str_repeat('a', 64);

        self::assertNull(SourceStamp::read("# Titre\n"));
        self::assertNull(SourceStamp::read("\n<!-- phil-dev source-sha256=".$hash." -->\n"));
        self::assertNull(SourceStamp::read('<!-- phil-dev source-sha256='.$hash.' -->'));
        self::assertNull(SourceStamp::read('<!-- phil-dev source-sha256='.str_repeat('a', 63)." -->\n"));
        self::assertNull(SourceStamp::read('<!-- phil-dev source-sha256='.str_repeat('g', 64)." -->\n"));
        self::assertSame('<!-- phil-dev source-sha256='.hash('sha256', 'x')." -->\n\n<!-- phil-dev source-sha256=".$hash." -->\n", SourceStamp::apply("\n<!-- phil-dev source-sha256=".$hash." -->\n", 'x'));
    }
}
