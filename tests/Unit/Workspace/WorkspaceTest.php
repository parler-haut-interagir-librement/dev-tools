<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Workspace;

use Phil\DevTools\Exception\WorkspaceNotFound;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Workspace\Manifest;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\PackageRole;
use Phil\DevTools\Workspace\Workspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Workspace::class)]
#[CoversClass(Package::class)]
#[CoversClass(PackageRole::class)]
#[CoversClass(WorkspaceNotFound::class)]
final class WorkspaceTest extends TestCase
{
    use TemporaryDirectory;

    public function testItFindsTheMembersAndTheInstalledPackages(): void
    {
        $root = $this->fixture('monorepo');
        $workspace = Workspace::load($root);

        self::assertTrue($workspace->isMonorepo());
        self::assertSame('phil/example-monorepo', $workspace->root->name);
        self::assertSame(PackageRole::Root, $workspace->root->role);
        self::assertTrue($workspace->root->isRoot());
        self::assertSame(['packages/slug', 'packages/slug-bundle', 'packages/tilttek-dice'], array_map(static fn (Package $package): string => $package->relative, $workspace->members));
        self::assertSame(['phil/resource'], array_map(static fn (Package $package): string => $package->name, $workspace->installed));
        self::assertSame(PackageRole::Installed, $workspace->installed[0]->role);
        self::assertSame('vendor/phil/resource', $workspace->installed[0]->relative);
        self::assertCount(5, $workspace->known());
        self::assertCount(4, $workspace->analysed());
        self::assertSame(['packages/*'], $workspace->packageGlobs());
    }

    public function testItNarrowsWhatIsAnalysed(): void
    {
        $workspace = Workspace::load($this->fixture('monorepo'), ['slug-bundle', 'tilttek/dice', 'packages/slug']);

        self::assertSame(['phil/slug', 'phil/slug-bundle', 'tilttek/dice'], array_map(static fn (Package $package): string => $package->name, $workspace->analysed()));
        self::assertSame(['phil/slug', 'phil/slug-bundle', 'tilttek/dice'], array_map(static fn (Package $package): string => $package->name, $workspace->targets()));
        self::assertCount(5, $workspace->known());
    }

    public function testOneMemberAfterOthers(): void
    {
        $workspace = Workspace::load($this->fixture('monorepo'), ['tilttek-dice']);

        self::assertSame([$workspace->members[2]], $workspace->targets());
        self::assertSame([$workspace->members[2]], $workspace->analysed());
    }

    public function testTheGivenListWinsOverTheVariable(): void
    {
        $root = $this->fixture('monorepo');
        putenv('PHIL_DEV_PACKAGES=slug');

        try {
            $workspace = Workspace::load($root, ['tilttek-dice']);
        } finally {
            putenv('PHIL_DEV_PACKAGES');
        }

        self::assertSame(['tilttek/dice'], array_map(static fn (Package $package): string => $package->name, $workspace->analysed()));
    }

    public function testInstalledPackages(): void
    {
        $root = $this->fixture('monorepo');
        $this->put($root.'/vendor/tilttek/activity/composer.json', '{"name": "tilttek/activity"}');
        $this->put($root.'/vendor/tilttek/activity/phil-dev.php', '<?php return [];');
        $this->put($root.'/vendor/phil/example-monorepo/composer.json', '{"name": "phil/example-monorepo"}');
        $this->put($root.'/vendor/phil/example-monorepo/phil-dev.php', '<?php return [];');
        $this->put($root.'/vendor/phil/slug/composer.json', '{"name": "phil/slug"}');
        $this->put($root.'/vendor/phil/slug/phil-dev.php', '<?php return [];');

        $workspace = Workspace::load($root);

        self::assertSame(['phil/resource', 'tilttek/activity'], array_map(static fn (Package $package): string => $package->name, $workspace->installed));
        self::assertSame(['phil/example-monorepo', 'phil/slug', 'phil/slug-bundle', 'tilttek/dice', 'phil/resource', 'tilttek/activity'], array_map(static fn (Package $package): string => $package->name, $workspace->known()));
    }

    public function testTheDefaultGlob(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/packages/one/composer.json', '{"name": "phil/one"}');

        self::assertSame(['phil/one'], array_map(static fn (Package $package): string => $package->name, Workspace::load($root)->members));
    }

    public function testTheRootIsAnalysedWhenNamed(): void
    {
        $workspace = Workspace::load($this->fixture('monorepo'), ['phil/example-monorepo']);

        self::assertSame(['phil/example-monorepo'], array_map(static fn (Package $package): string => $package->name, $workspace->analysed()));
        self::assertSame([], $workspace->targets());
    }

    public function testTheRootIsNotSelectedByItsDirectoryName(): void
    {
        $root = $this->fixture('monorepo');
        $workspace = Workspace::load($root, [basename($root), '']);

        self::assertSame([], $workspace->analysed());
    }

    public function testItReadsTheVariable(): void
    {
        $root = $this->fixture('monorepo');
        putenv('PHIL_DEV_PACKAGES= slug , ,tilttek-dice');

        try {
            $workspace = Workspace::load($root);
        } finally {
            putenv('PHIL_DEV_PACKAGES');
        }

        self::assertSame(['phil/slug', 'tilttek/dice'], array_map(static fn (Package $package): string => $package->name, $workspace->analysed()));
    }

    public function testAnEmptyVariableNarrowsNothing(): void
    {
        $root = $this->fixture('monorepo');
        putenv('PHIL_DEV_PACKAGES=  ');

        try {
            $workspace = Workspace::load($root);
        } finally {
            putenv('PHIL_DEV_PACKAGES');
        }

        self::assertCount(4, $workspace->analysed());
        self::assertCount(3, $workspace->targets());
    }

    public function testTargetsByName(): void
    {
        $workspace = Workspace::load($this->fixture('monorepo'));

        self::assertSame(['tilttek/dice', 'phil/slug'], array_map(static fn (Package $package): string => $package->name, $workspace->targets(['tilttek-dice', 'phil/slug'])));
        self::assertSame('phil/slug-bundle', $workspace->member('packages/slug-bundle')?->name);
        self::assertNull($workspace->member('nothing'));
        self::assertSame('phil/resource', $workspace->byName('phil/resource')?->name);
        self::assertNull($workspace->byName('phil/nothing'));
    }

    public function testAnUnknownTargetIsRefused(): void
    {
        $workspace = Workspace::load($this->fixture('monorepo'));

        $this->expectException(WorkspaceNotFound::class);
        $this->expectExceptionMessageIsOrContains('No package "nothing" in this monorepo.');

        $workspace->targets(['nothing']);
    }

    public function testAPackageOnItsOwnIsItsOwnTarget(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/alone"}');

        $workspace = Workspace::load($root, ['other']);

        self::assertFalse($workspace->isMonorepo());
        self::assertSame([$workspace->root], $workspace->targets());
        self::assertSame([], $workspace->installed);
    }

    public function testItNeedsAComposerJson(): void
    {
        $this->expectException(WorkspaceNotFound::class);
        $this->expectExceptionMessageIsOrContains('holds no composer.json.');

        Workspace::load($this->temporaryDirectory().'/nowhere');
    }

    public function testTheRootGlobsComeFromItsManifest(): void
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/'.Manifest::FILE, "<?php return ['packages' => ['libs/*', 'apps/*']];");
        $this->put($root.'/apps/app/composer.json', '{"name": "phil/app"}');
        $this->put($root.'/libs/one/composer.json', '{"name": "phil/one"}');
        $this->put($root.'/libs/empty/README.md', '');
        $this->put($root.'/packages/ignored/composer.json', '{"name": "phil/ignored"}');

        $workspace = Workspace::load($root);

        self::assertSame(['phil/app', 'phil/one'], array_map(static fn (Package $package): string => $package->name, $workspace->members));
    }

    public function testPlannedMembersAreAnalysedByDefault(): void
    {
        $workspace = Workspace::load($this->withPlanned(), null, false);

        self::assertSame(['phil/root', 'phil/active', 'phil/later', 'tilttek/later'], $this->names($workspace->analysed()));
        self::assertSame(['phil/active', 'phil/later', 'tilttek/later'], $this->names($workspace->targets()));
        self::assertSame([], $workspace->skipped());
        self::assertFalse($workspace->skipsPlanned());
    }

    public function testPlannedMembersAreSkippedWhenAsked(): void
    {
        $workspace = Workspace::load($this->withPlanned(), null, true);

        self::assertTrue($workspace->skipsPlanned());
        self::assertSame(['phil/root', 'phil/active'], $this->names($workspace->analysed()));
        self::assertSame(['phil/active'], $this->names($workspace->targets()));
        self::assertSame(['phil/later', 'tilttek/later'], $this->names($workspace->skipped()));
        self::assertCount(3, $workspace->members);
        self::assertSame(['phil/later'], $this->names($workspace->targets(['later'])));
        self::assertTrue($workspace->members[1]->isPlanned());
        self::assertFalse($workspace->members[0]->isPlanned());
    }

    public function testAPlannedRootIsNeverSkipped(): void
    {
        $root = $this->withPlanned();
        $this->put($root.'/phil-dev.php', "<?php return ['status' => 'planned'];");

        $workspace = Workspace::load($root, null, true);

        self::assertSame(['phil/root', 'phil/active'], $this->names($workspace->analysed()));
        self::assertSame(['phil/later', 'tilttek/later'], $this->names($workspace->skipped()));
    }

    public function testSkippingCombinesWithTheNarrowing(): void
    {
        $workspace = Workspace::load($this->withPlanned(), ['active', 'tilttek-later'], true);

        self::assertSame(['phil/active'], $this->names($workspace->analysed()));
        self::assertSame(['tilttek/later'], $this->names($workspace->skipped()));
    }

    public function testItReadsTheSkipVariable(): void
    {
        $root = $this->withPlanned();
        $read = static function (string $value) use ($root): Workspace {
            putenv('PHIL_DEV_SKIP_PLANNED='.$value);

            try {
                return Workspace::load($root);
            } finally {
                putenv('PHIL_DEV_SKIP_PLANNED');
            }
        };

        self::assertSame(['phil/active'], $this->names($read('1')->targets()));
        self::assertSame(['phil/active'], $this->names($read(' TRUE ')->targets()));
        self::assertSame(['phil/active'], $this->names($read('yes')->targets()));
        self::assertCount(3, $read('0')->targets());
        self::assertCount(3, $read('false')->targets());
        self::assertCount(3, $read(' ')->targets());
        self::assertCount(3, Workspace::load($root)->targets());
        self::assertSame('PHIL_DEV_SKIP_PLANNED', Workspace::SKIP_PLANNED_VARIABLE);
    }

    public function testTheGivenSkipWinsOverTheVariable(): void
    {
        $root = $this->withPlanned();
        putenv('PHIL_DEV_SKIP_PLANNED=1');

        try {
            $workspace = Workspace::load($root, null, false);
        } finally {
            putenv('PHIL_DEV_SKIP_PLANNED');
        }

        self::assertCount(3, $workspace->targets());
        self::assertFalse(Workspace::skipPlannedFromEnvironment());
    }

    public function testADirectoryWithoutComposerJson(): void
    {
        $this->expectException(WorkspaceNotFound::class);
        $this->expectExceptionMessageIsOrContains('holds no composer.json.');

        Workspace::load($this->temporaryDirectory());
    }

    /**
     * A monorepo with one active member and two planned ones.
     */
    private function withPlanned(): string
    {
        $root = $this->temporaryDirectory();
        $this->put($root.'/composer.json', '{"name": "phil/root"}');
        $this->put($root.'/packages/active/composer.json', '{"name": "phil/active"}');
        $this->put($root.'/packages/later/composer.json', '{"name": "phil/later"}');
        $this->put($root.'/packages/later/phil-dev.php', "<?php return ['status' => 'planned'];");
        $this->put($root.'/packages/tilttek-later/composer.json', '{"name": "tilttek/later"}');
        $this->put($root.'/packages/tilttek-later/phil-dev.php', "<?php return ['status' => 'planned'];");

        return $root;
    }

    /**
     * @param list<Package> $packages
     *
     * @return list<string>
     */
    private function names(array $packages): array
    {
        return array_map(static fn (Package $package): string => $package->name, $packages);
    }
}
