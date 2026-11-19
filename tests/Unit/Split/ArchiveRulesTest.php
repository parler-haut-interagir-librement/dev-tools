<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Split;

use Phil\DevTools\Files;
use Phil\DevTools\Split\ArchivePattern;
use Phil\DevTools\Split\ArchiveRules;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArchiveRules::class)]
#[CoversClass(ArchivePattern::class)]
final class ArchiveRulesTest extends TestCase
{
    use TemporaryDirectory;

    public function testTheTestsAutoloadDevNamesAreLeftOutByGitAttributes(): void
    {
        $root = $this->package("# What the archive leaves out\n/tests export-ignore\n/.github export-ignore\n*.dist  -text  export-ignore\n");
        $composer = $this->composer(['autoload-dev' => ['psr-4' => ['Phil\\X\\Tests\\' => 'tests/']]]);

        self::assertSame(
            ['the archive leaves out tests/ (autoload-dev psr-4), through "/tests" in .gitattributes'],
            ArchiveRules::of($root, $composer)->missing($root, $composer),
        );
    }

    public function testNothingIsMissingWhenTheArchiveKeepsEverythingItNeeds(): void
    {
        $root = $this->package("/.github export-ignore\n/phpunit.dist.xml export-ignore\n/tests/Fixtures/big export-ignore\n");
        $composer = $this->composer(['autoload-dev' => ['psr-4' => ['Phil\\X\\Tests\\' => 'tests/']]]);

        self::assertSame([], ArchiveRules::of($root, $composer)->missing($root, $composer));
    }

    public function testArchiveExcludeOfComposerJson(): void
    {
        $root = $this->package();
        $composer = $this->composer([
            'autoload' => ['files' => ['src/functions.php'], 'classmap' => ['lib/'], 'psr-0' => ['Old_' => 'legacy/']],
            'archive' => ['exclude' => ['/src/functions.php', 'lib', 'legacy/', 'README.md', '!README.md', '', 7]],
        ]);
        $this->put($root.'/legacy/Old/A.php', '<?php');
        $this->put($root.'/src/functions.php', '<?php');
        $this->put($root.'/lib/A.php', '<?php');

        self::assertSame([
            'the archive leaves out legacy/ (autoload psr-0), through "legacy/" in archive.exclude',
            'the archive leaves out lib/ (autoload classmap), through "lib" in archive.exclude',
            'the archive leaves out src/functions.php (autoload files), through "/src/functions.php" in archive.exclude',
        ], ArchiveRules::of($root, $composer)->missing($root, $composer));
    }

    public function testTheEssentialFiles(): void
    {
        $root = $this->package("LICENSE export-ignore\n*.md export-ignore\ncomposer.json export-ignore\n");
        $composer = $this->composer(['autoload' => ['psr-4' => ['Phil\\X\\' => './src/']]]);

        self::assertSame([
            'the archive leaves out LICENSE (essential), through "LICENSE" in .gitattributes',
            'the archive leaves out README.md (essential), through "*.md" in .gitattributes',
        ], ArchiveRules::of($root, $composer)->missing($root, $composer));
    }

    public function testAPathTheAutoloadNamesButThePackageLacksIsNotReported(): void
    {
        $root = $this->package("/tests export-ignore\n");
        $composer = $this->composer(['autoload-dev' => ['psr-4' => ['A\\' => ['tests/', 'spec/']]]]);
        Files::removeDirectory($root.'/tests');

        self::assertSame([], ArchiveRules::of($root, $composer)->missing($root, $composer));
    }

    /**
     * @param list<string> $patterns
     */
    #[DataProvider('patterns')]
    public function testThePatterns(array $patterns, string $path, bool $directory, bool $excluded): void
    {
        $root = $this->temporaryDirectory();

        self::assertSame($excluded, ArchiveRules::of($root, ['archive' => ['exclude' => $patterns]])->excludes($path, $directory));
    }

    /**
     * @return iterable<string, array{list<string>, string, bool, bool}>
     */
    public static function patterns(): iterable
    {
        yield 'an unanchored name at any depth' => [['Fixtures'], 'tests/Fixtures', true, true];
        yield 'an unanchored name inside a directory left out' => [['Fixtures'], 'tests/Fixtures/a.php', false, true];
        yield 'a name that only ends the same' => [['Fixtures'], 'tests/MyFixtures', true, false];
        yield 'an anchored path at the root only' => [['/tests'], 'src/tests', true, false];
        yield 'a path with an inner slash is anchored' => [['tests/Fixtures'], 'src/tests/Fixtures', true, false];
        yield 'a path with an inner slash at the root' => [['tests/Fixtures'], 'tests/Fixtures/a.php', false, true];
        yield 'a trailing slash matches a directory' => [['build/'], 'build', true, true];
        yield 'a trailing slash does not match a file' => [['build/'], 'build', false, false];
        yield 'a trailing slash matches the directory above a file' => [['build/'], 'build/a.php', false, true];
        yield 'a star stops at a slash' => [['/tests/*.php'], 'tests/Unit/ATest.php', false, false];
        yield 'a star within a segment' => [['/tests/*.php'], 'tests/bootstrap.php', false, true];
        yield 'a double star crosses slashes' => [['/tests/**/*.php'], 'tests/Unit/Deep/ATest.php', false, true];
        yield 'a double star matches no directory' => [['/tests/**/*.php'], 'tests/bootstrap.php', false, true];
        yield 'a trailing double star' => [['/doc/**'], 'doc/fr/index.md', false, true];
        yield 'a question mark is one character' => [['?.md'], 'a.md', false, true];
        yield 'a question mark is not a slash' => [['/a?b'], 'a/b', false, false];
        yield 'a class' => [['[ab].txt'], 'b.txt', false, true];
        yield 'a class that does not match' => [['[ab].txt'], 'c.txt', false, false];
        yield 'a negated class' => [['[!ab].txt'], 'c.txt', false, true];
        yield 'a negated class that does not match' => [['[!ab].txt'], 'a.txt', false, false];
        yield 'a range' => [['[a-c].txt'], 'b.txt', false, true];
        yield 'a range that does not match' => [['[a-c].txt'], 'd.txt', false, false];
        yield 'a range of one character' => [['[b-b].txt'], 'b.txt', false, true];
        yield 'a negated range' => [['[!a-c].txt'], 'd.txt', false, true];
        yield 'a range and a character' => [['[a-cx].txt'], 'x.txt', false, true];
        yield 'a dash at the end is a dash' => [['[a-].txt'], '-.txt', false, true];
        yield 'a dash at the end is not a range' => [['[a-].txt'], 'b.txt', false, false];
        yield 'a backwards range is literal' => [['[c-a].txt'], '[c-a].txt', false, true];
        yield 'a backwards range matches no character' => [['[c-a].txt'], 'b.txt', false, false];
        yield 'a negation alone is literal' => [['[!].txt'], '[!].txt', false, true];
        yield 'the delimiter in a class' => [['[#].txt'], '#.txt', false, true];
        yield 'a dot in a class is a dot' => [['[.].txt'], 'x.txt', false, false];
        yield 'a backslash in a class' => [['[\\].txt'], '\\.txt', false, true];
        yield 'a bracket last is literal' => [['a['], 'a[', false, true];
        yield 'a bracket and one character last is literal' => [['a[b'], 'a[b', false, true];
        yield 'a bracket without its end is literal' => [['[ab.txt'], '[ab.txt', false, true];
        yield 'an empty class is literal' => [['[].txt'], '[].txt', false, true];
        yield 'a dot is literal' => [['a.b'], 'axb', false, false];
        yield 'the last rule decides' => [['/tests', '!/tests'], 'tests', true, false];
        yield 'a rule taken back is taken again' => [['/tests', '!/tests', '/tests'], 'tests', true, true];
        yield 'a parent left out is not taken back by a child rule' => [['/tests', '!/tests/Unit'], 'tests/Unit/ATest.php', false, true];
        yield 'a path written with dot slash' => [['/tests'], './tests/', true, true];
        yield 'padding around a pattern' => [['  /tests  '], 'tests', true, true];
        yield 'nothing matches' => [['/vendor'], 'src', true, false];
    }

    public function testAttributesWithoutExportIgnoreAndComments(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/.gitattributes', "* text=auto\r\n#/src export-ignore\n/tests -export-ignore\n\n/doc linguist-documentation export-ignore\r\n/bin export-ignore\r/spec export-ignore");
        $rules = ArchiveRules::of($root, []);

        self::assertFalse($rules->excludes('src', true));
        self::assertFalse($rules->excludes('tests', true));
        self::assertTrue($rules->excludes('doc', true));
        self::assertTrue($rules->excludes('bin', true));
        self::assertTrue($rules->excludes('spec', true));
    }

    public function testAnArchiveSectionThatIsNotAList(): void
    {
        $root = $this->temporaryDirectory();

        self::assertFalse(ArchiveRules::of($root, ['archive' => 'src'])->excludes('src', true));
        self::assertFalse(ArchiveRules::of($root, ['archive' => ['exclude' => 'src']])->excludes('src', true));
    }

    public function testAnAutoloadSectionThatIsNotAMap(): void
    {
        $root = $this->package("/src export-ignore\n");

        self::assertSame([], ArchiveRules::of($root, [])->missing($root, ['autoload' => 'src/', 'autoload-dev' => ['psr-4' => 'tests/', 'files' => [3]]]));
    }

    /**
     * A package with src/, tests/, composer.json, LICENSE and README.md, and its .gitattributes when given.
     */
    private function package(?string $attributes = null): string
    {
        $root = $this->temporaryDirectory();

        foreach (['src/A.php', 'tests/ATest.php', 'composer.json', 'LICENSE', 'README.md'] as $file) {
            $this->put($root.'/'.$file, '');
        }

        if (null !== $attributes) {
            $this->put($root.'/.gitattributes', $attributes);
        }

        return $root;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function composer(array $extra): array
    {
        return ['name' => 'phil/x', 'autoload' => ['psr-4' => ['Phil\\X\\' => 'src/']], ...$extra];
    }
}
