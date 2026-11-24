<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Split;

use Phil\DevTools\DevTools;
use Phil\DevTools\Split\SecurityFiles;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityFiles::class)]
final class SecurityFilesTest extends TestCase
{
    use TemporaryDirectory;

    public function testAPackageWithoutScripts(): void
    {
        $files = SecurityFiles::forPackage($this->temporaryDirectory(), '1.x');

        self::assertSame([SecurityFiles::DEPENDABOT, SecurityFiles::SCORECARD, SecurityFiles::ZIZMOR], array_keys($files));
        self::assertStringEndsWith(
            "version: 2\nupdates:\n"
            ."  - package-ecosystem: composer\n    directory: /\n    schedule:\n      interval: weekly\n    open-pull-requests-limit: 0\n    cooldown:\n      default-days: 7\n"
            ."  - package-ecosystem: github-actions\n    directory: /\n    schedule:\n      interval: weekly\n    open-pull-requests-limit: 0\n    cooldown:\n      default-days: 7\n",
            $files[SecurityFiles::DEPENDABOT],
        );
        self::assertStringStartsWith('# The dependencies of this read-only split repository', $files[SecurityFiles::DEPENDABOT]);
        self::assertStringContainsString("    branches: ['1.x']\n", $files[SecurityFiles::SCORECARD]);
        self::assertStringNotContainsString('{{', $files[SecurityFiles::SCORECARD]);
        self::assertSame(4, preg_match_all('/uses: [a-z\/-]+@[0-9a-f]{40} # v\d+\.\d+\.\d+$/m', $files[SecurityFiles::SCORECARD]));
        self::assertDoesNotMatchRegularExpression('/uses: [^@]+@(?![0-9a-f]{40} )/', $files[SecurityFiles::SCORECARD], 'every action is pinned to a commit');
        self::assertStringContainsString("    branches: ['1.x']\n", $files[SecurityFiles::ZIZMOR]);
        self::assertStringNotContainsString('{{', $files[SecurityFiles::ZIZMOR]);
        self::assertSame(2, preg_match_all('/uses: [a-z\/-]+@[0-9a-f]{40} # v\d+\.\d+\.\d+$/m', $files[SecurityFiles::ZIZMOR]));
        self::assertStringContainsString("permissions: {}\n", $files[SecurityFiles::ZIZMOR]);
        self::assertStringContainsString("persist-credentials: false\n", $files[SecurityFiles::ZIZMOR]);
    }

    public function testTheFilesOfAMonorepo(): void
    {
        $dependabot = (string) file_get_contents(DevTools::resource(SecurityFiles::MONOREPO[SecurityFiles::DEPENDABOT]));
        $zizmor = (string) file_get_contents(DevTools::resource(SecurityFiles::MONOREPO[SecurityFiles::ZIZMOR]));

        self::assertSame(3, substr_count($dependabot, "    cooldown:\n      default-days: 7\n"));
        self::assertSame(3, substr_count($dependabot, '    groups:'));
        self::assertStringNotContainsString('open-pull-requests-limit', $dependabot);
        self::assertSame(2, preg_match_all('/uses: [a-z\/-]+@[0-9a-f]{40} # v\d+\.\d+\.\d+$/m', $zizmor));
        self::assertStringContainsString("    branches: ['main']\n", $zizmor);
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function scripts(): iterable
    {
        yield 'at the root' => [['package.json'], ['/']];
        yield 'under assets' => [['assets/package.json'], ['/assets']];
        yield 'both' => [['package.json', 'assets/package.json'], ['/', '/assets']];
    }

    /**
     * @param list<string> $files
     * @param list<string> $directories
     */
    #[DataProvider('scripts')]
    public function testThePackagesOfNpm(array $files, array $directories): void
    {
        $directory = $this->temporaryDirectory();

        foreach ($files as $file) {
            $this->put($directory.'/'.$file, '{}');
        }

        $dependabot = SecurityFiles::forPackage($directory, 'main')[SecurityFiles::DEPENDABOT];

        self::assertSame(\count($directories), preg_match_all('/package-ecosystem: npm\n    directory: (\S+)\n/', $dependabot, $matches));
        self::assertSame($directories, $matches[1]);
        self::assertLessThan(strpos($dependabot, 'npm'), strpos($dependabot, 'composer'));
        self::assertLessThan(strpos($dependabot, 'github-actions'), strpos($dependabot, 'npm'));
    }

    public function testADirectoryNamedPackageJsonIsNoPackage(): void
    {
        $directory = $this->temporaryDirectory();
        mkdir($directory.'/package.json');

        self::assertStringNotContainsString('npm', SecurityFiles::forPackage($directory, 'main')[SecurityFiles::DEPENDABOT]);
    }

    /**
     * @return iterable<string, array{string, array{string, string}|null}>
     */
    public static function urls(): iterable
    {
        yield 'SSH' => ['git@github.com:parler-haut-interagir-librement/slug.git', ['parler-haut-interagir-librement', 'slug']];
        yield 'SSH URL' => ['ssh://git@github.com/TiltTek/dice.git', ['TiltTek', 'dice']];
        yield 'HTTPS' => ['https://github.com/owner/name.php', ['owner', 'name.php']];
        yield 'HTTPS with a slash' => ['https://github.com/owner/name/', ['owner', 'name']];
        yield 'another host' => ['git@gitlab.com:owner/name.git', null];
        yield 'a path' => ['/tmp/remote', null];
        yield 'more after the name' => ['https://github.com/owner/name/tree/main', null];
        yield 'a prefix' => ['xgit@github.com:owner/name.git', null];
        yield 'no name' => ['https://github.com/owner/', null];
    }

    /**
     * @param array{string, string}|null $expected
     */
    #[DataProvider('urls')]
    public function testTheRepositoryOfAUrl(string $url, ?array $expected): void
    {
        self::assertSame($expected, SecurityFiles::gitHubRepository($url));
    }

    public function testTheRepositoryOfAPackage(): void
    {
        self::assertSame(['o', 'n'], SecurityFiles::repositoryOf('phil/slug', 'git@github.com:o/n.git', null));
        self::assertNull(SecurityFiles::repositoryOf('phil/slug', '/tmp/remote', null));
        self::assertSame(['parler-haut-interagir-librement', 'slug'], SecurityFiles::repositoryOf('phil/slug', 'git@github.com:o/packages.git', 'packages/slug'));
        self::assertSame(['parler-haut-interagir-librement', 'slug'], SecurityFiles::repositoryOf('phil/slug', null, null));
        self::assertSame(['TiltTek', 'dice'], SecurityFiles::repositoryOf('tilttek/dice', null, null));
        self::assertNull(SecurityFiles::repositoryOf('acme/thing', null, null));
        self::assertSame(['parler-haut-interagir-librement', 'a/b'], SecurityFiles::repositoryOf('phil/a/b', null, null));
        self::assertNull(SecurityFiles::repositoryOf('phil', null, null));
        self::assertNull(SecurityFiles::repositoryOf('phil/', null, null));
    }

    public function testTheBadge(): void
    {
        $badge = SecurityFiles::badge('owner', 'name');

        self::assertSame('[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/owner/name/badge)](https://scorecard.dev/viewer/?uri=github.com/owner/name)', $badge);
        self::assertTrue(SecurityFiles::hasBadge("# x\n\n".$badge."\n"));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function badges(): iterable
    {
        yield 'Scorecard' => ['[![s](https://api.scorecard.dev/projects/github.com/o/n/badge)](x)', true];
        yield 'Scorecard, older host' => ['[![s](https://api.securityscorecards.dev/projects/github.com/o/n/badge)](x)', true];
        yield 'Snyk' => ['[![Known Vulnerabilities](https://snyk.io/test/github/o/n/badge.svg)](x)', true];
        yield 'none' => ['# A README', false];
        yield 'a link to Scorecard, not an image' => ['See https://api.scorecard.dev/projects/github.com/o/n/badge.', false];
    }

    #[DataProvider('badges')]
    public function testWhetherAReadmeHasABadge(string $readme, bool $expected): void
    {
        self::assertSame($expected, SecurityFiles::hasBadge($readme));
    }

    public function testTheBadgeGoesAfterTheFirstTitle(): void
    {
        self::assertSame("# Title\n\nB\n\nText\n# Other\n", SecurityFiles::withBadge("# Title\n\nText\n# Other\n", 'B'));
        self::assertSame("<!-- stamp -->\n# Titre\n\nB\n\nTexte\n", SecurityFiles::withBadge("<!-- stamp -->\n# Titre\n\nTexte\n", 'B'));
        self::assertSame("B\n\n## Not a title\n", SecurityFiles::withBadge("## Not a title\n", 'B'));
        self::assertSame("B\n\n# No end of line", SecurityFiles::withBadge('# No end of line', 'B'));
    }
}
