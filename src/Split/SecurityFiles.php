<?php

declare(strict_types=1);

namespace Phil\DevTools\Split;

use Phil\DevTools\DevTools;
use Phil\DevTools\Files;

/**
 * The security files of a split repository, and the badge of its README.
 *
 * `phil-dev split` adds `.github/dependabot.yml` and `.github/workflows/scorecard.yml` to each split, in a commit on top
 * of the split commit: Dependabot watches the Composer dependencies, the npm ones when the package has a `package.json`
 * at its root or under `assets/`, and the GitHub Actions of the workflow; the OpenSSF Scorecard workflow publishes the
 * score the badge shows. The badge needs no account: Scorecard publishes with the workflow's OIDC token.
 */
final class SecurityFiles
{
    /**
     * The GitHub owner of the split repositories of each vendor, when a manifest names no repository.
     *
     * @var array<string, string>
     */
    public const array OWNERS = ['phil' => 'parler-haut-interagir-librement', 'tilttek' => 'TiltTek'];

    public const string DEPENDABOT = '.github/dependabot.yml';

    public const string SCORECARD = '.github/workflows/scorecard.yml';

    public const string ZIZMOR = '.github/workflows/zizmor.yml';

    /**
     * The files a monorepo gets at its root, by path, each with its resource.
     *
     * @var array<string, string>
     */
    public const array MONOREPO = [self::DEPENDABOT => 'security/monorepo/dependabot.yml', self::ZIZMOR => 'security/monorepo/zizmor.yml'];

    /**
     * What a README shows when it has a security badge: an OpenSSF Scorecard, or a Snyk badge.
     */
    private const string BADGE_PATTERN = '#\]\(https://(?:api\.scorecard\.dev/projects|api\.securityscorecards\.dev/projects|snyk\.io/test)/github(?:\.com)?/#';

    /**
     * The files to add to the split of the package in `$directory`, by path in the split repository.
     *
     * @return array<string, string>
     */
    public static function forPackage(string $directory, string $branch): array
    {
        $updates = [self::update('composer', '/')];

        foreach (['' => '/', '/assets' => '/assets'] as $subdirectory => $target) {
            if (is_file($directory.$subdirectory.'/package.json')) {
                $updates[] = self::update('npm', $target);
            }
        }

        // The Scorecard and zizmor workflows are some, so that Dependabot also keeps their pinned actions current.
        $updates[] = self::update('github-actions', '/');

        return [
            self::DEPENDABOT => strtr(Files::read(DevTools::resource('security/split/dependabot.yml.tpl')), ['{{updates}}' => implode("\n", $updates)]),
            self::SCORECARD => strtr(Files::read(DevTools::resource('security/split/scorecard.yml.tpl')), ['{{branch}}' => $branch]),
            self::ZIZMOR => strtr(Files::read(DevTools::resource('security/split/zizmor.yml.tpl')), ['{{branch}}' => $branch]),
        ];
    }

    /**
     * The owner and the name of a GitHub repository, from its URL: `git@github.com:owner/name.git`,
     * `ssh://git@github.com/owner/name.git` or `https://github.com/owner/name`. Null for any other URL.
     *
     * @return array{string, string}|null
     */
    public static function gitHubRepository(string $url): ?array
    {
        if (1 !== preg_match('#^(?:git@github\.com:|ssh://git@github\.com/|https://github\.com/)([A-Za-z0-9-]+)/([A-Za-z0-9._-]+?)(?:\.git)?/?$#', $url, $parts)) {
            return null;
        }

        return [$parts[1], $parts[2]];
    }

    /**
     * The GitHub repository a package is split to: its `split.repository` when it names one on GitHub and no prefix,
     * else the repository of its name under the owner of its vendor. Null when neither is known.
     *
     * @return array{string, string}|null
     */
    public static function repositoryOf(string $package, mixed $repository, mixed $prefix): ?array
    {
        if (\is_string($repository) && null === $prefix) {
            return self::gitHubRepository($repository);
        }

        [$vendor, $name] = explode('/', $package, 2) + ['', ''];

        return isset(self::OWNERS[$vendor]) && '' !== $name ? [self::OWNERS[$vendor], $name] : null;
    }

    /**
     * The OpenSSF Scorecard badge of a GitHub repository, as a line of Markdown.
     */
    public static function badge(string $owner, string $name): string
    {
        $repository = 'github.com/'.$owner.'/'.$name;

        return '[![OpenSSF Scorecard](https://api.scorecard.dev/projects/'.$repository.'/badge)](https://scorecard.dev/viewer/?uri='.$repository.')';
    }

    public static function hasBadge(string $readme): bool
    {
        return 1 === preg_match(self::BADGE_PATTERN, $readme);
    }

    /**
     * The README with the badge on its own line after its first title, or at its top when it has no title.
     */
    public static function withBadge(string $readme, string $badge): string
    {
        if (1 === preg_match('/^# [^\n]*\n/m', $readme, $title, \PREG_OFFSET_CAPTURE)) {
            $end = $title[0][1] + \strlen($title[0][0]);

            return substr($readme, 0, $end)."\n".$badge."\n".substr($readme, $end);
        }

        return $badge."\n\n".$readme;
    }

    private static function update(string $ecosystem, string $directory): string
    {
        return '  - package-ecosystem: '.$ecosystem."\n"
            .'    directory: '.$directory."\n"
            ."    schedule:\n"
            ."      interval: weekly\n"
            ."    open-pull-requests-limit: 0\n"
            ."    cooldown:\n"
            .'      default-days: 7';
    }
}
