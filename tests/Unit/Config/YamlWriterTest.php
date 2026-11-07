<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Config;

use Phil\DevTools\Config\YamlWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(YamlWriter::class)]
final class YamlWriterTest extends TestCase
{
    public function testItWritesMapsListsAndScalars(): void
    {
        $yaml = YamlWriter::write([
            'name' => "it's",
            'count' => 3,
            'on' => true,
            'off' => false,
            'none' => null,
            'empty' => [],
            'odd key' => 'x',
            'list' => ['a', ['b' => 'c', 'd' => ['e']], []],
            'object' => new stdClass(),
            'float' => 1.5,
        ], "One\n");

        self::assertSame(<<<'YAML'
            # One
            #
            name: 'it''s'
            count: 3
            on: true
            off: false
            none: ~
            empty: []
            'odd key': 'x'
            list:
              - 'a'
              - b: 'c'
                d:
                  - 'e'
              - []
            object: 'stdClass'
            float: 'float'

            YAML, $yaml);
    }

    public function testWithoutAHeader(): void
    {
        self::assertSame("a: 'b'\n", YamlWriter::write(['a' => 'b']));
    }
}
