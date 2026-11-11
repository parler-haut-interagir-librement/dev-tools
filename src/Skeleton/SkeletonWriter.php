<?php

declare(strict_types=1);

namespace Phil\DevTools\Skeleton;

use FilesystemIterator;
use Phil\DevTools\DevTools;
use Phil\DevTools\Docs\Mirrors;
use Phil\DevTools\Docs\SourceStamp;
use Phil\DevTools\Exception\InvalidPackageName;
use Phil\DevTools\Files;
use Phil\DevTools\Matrix\Matrix;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Writes the skeleton of a new package: the file set every package has, for a library or for a bundle, in English,
 * with the wording of its vendor, and the French mirror of its README and `doc/`, stamped with its English source.
 *
 * A planned package — one being written — also gets `'status' => 'planned'` in its `phil-dev.php`, a README that
 * describes what it will do, and a smoke test: its bundle class for a bundle, its autoloaded namespace for a library.
 * The packages it requires go in its `composer.json`, unused until its code uses them.
 */
final readonly class SkeletonWriter
{
    /** @var array<string, string> */
    private const array INTRODUCTIONS = [
        'phil' => 'This package is one of the open packages of [Parler Haut Interagir Librement](https://github.com/parler-haut-interagir-librement)'
            ."\n(PHIL). Its code is developed in the PHIL monorepo, with the other `phil/*` packages, and its own repository is a"
            ."\nread-only copy split from it: **open issues and pull requests on the monorepo**, not on the split repository.",
        'tilttek' => 'This package is one of the open `tilttek/*` packages of Tilttek. Its code is developed in the monorepo of'
            ."\n[Parler Haut Interagir Librement](https://github.com/parler-haut-interagir-librement) (PHIL), with the `phil/*`"
            ."\npackages whose rules it follows, and its own repository is a read-only copy split from it: **open issues and pull"
            ."\nrequests on the monorepo**, not on the split repository.",
    ];

    /** @var array<string, string> */
    private const array HOLDERS = [
        'phil' => 'a Parler Haut Interagir Librement project',
        'tilttek' => 'a Tilttek project',
    ];

    /**
     * The constraint on a package of the monorepos, required without one.
     */
    public const string SIBLING_CONSTRAINT = '^0.1@dev';

    /**
     * @param string $year the year of the copyright, such as "2026"
     */
    public function __construct(private string $year)
    {
    }

    /**
     * @param string       $description   what the package does; a planned package's first sentence is its summary
     * @param list<string> $requires      the packages it requires, `vendor/name` or `vendor/name:constraint`
     * @param string|null  $descriptionFr the French description; the English one by default
     * @param string|null  $title         the title of the README; the package's name by default
     *
     * @return list<string> the files written, relative to the directory
     */
    public function write(
        PackageName $name,
        string $directory,
        string $description,
        bool $libraryExists = false,
        bool $planned = false,
        array $requires = [],
        ?string $descriptionFr = null,
        ?string $title = null,
    ): array {
        if (is_dir($directory) && [] !== array_diff((array) scandir($directory), ['.', '..'])) {
            throw new InvalidPackageName(\sprintf('%s already exists and is not empty.', $directory));
        }

        $constraints = $this->constraints($requires);
        $values = [
            ...$this->values($name, $description, $libraryExists),
            ...$this->plannedValues($name, $planned, $description, $descriptionFr ?? $description),
            ...$this->requireValues(array_keys($constraints)),
            '{{title}}' => $title ?? $name->package(),
        ];
        $written = [];
        $sets = ['common', $name->bundle ? 'bundle' : 'library', ...($planned && !$name->bundle ? ['planned-library'] : [])];

        foreach ($sets as $set) {
            array_push($written, ...$this->writeSet($set, $name, $directory, $values, $planned));
        }

        sort($written);

        if ([] !== $constraints) {
            $this->require($directory.'/composer.json', $constraints);
        }

        foreach (array_filter($written, Mirrors::isMirror(...)) as $mirror) {
            $source = Files::read($directory.'/'.Mirrors::sourceOf($mirror));
            file_put_contents($directory.'/'.$mirror, SourceStamp::apply(Files::read($directory.'/'.$mirror), $source));
        }

        return $written;
    }

    /**
     * Writes the files of one set of the skeleton, the templates filled with the values.
     *
     * @param array<string, string> $values
     *
     * @return list<string> the files written, relative to the directory
     */
    private function writeSet(string $set, PackageName $name, string $directory, array $values, bool $planned): array
    {
        $written = [];
        $root = DevTools::resource('skeleton/'.$set);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative = substr($file->getPathname(), \strlen($root) + 1);

            // A planned library's smoke test takes the place of the empty directory of its tests.
            if ($planned && 'tests/Unit/.gitkeep' === $relative) {
                continue;
            }

            $target = str_replace('__BUNDLE_CLASS__', $name->bundleClass(), $relative);
            $isTemplate = str_ends_with($target, '.tpl');
            $target = $isTemplate ? substr($target, 0, -4) : $target;
            $content = Files::read($file->getPathname());

            if ($isTemplate) {
                $content = strtr($content, $values);
            }

            $path = $directory.'/'.$target;

            if (!is_dir(\dirname($path))) {
                mkdir(\dirname($path), recursive: true);
            }

            file_put_contents($path, $content);
            $written[] = $target;
        }

        return $written;
    }

    /**
     * The packages to require, each with its constraint: the one given after a colon, else that of the monorepos.
     *
     * @param list<string> $requires
     *
     * @return array<string, string>
     */
    private function constraints(array $requires): array
    {
        $constraints = [];

        foreach ($requires as $require) {
            if (1 !== preg_match('#^([a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*)(?::(\S+))?$#', $require, $parts)) {
                throw new InvalidPackageName(\sprintf('"%s" is not a package to require, such as phil/geo or symfony/uid:^8.2.', $require));
            }

            $constraints[$parts[1]] = $parts[2] ?? self::SIBLING_CONSTRAINT;
        }

        return $constraints;
    }

    /**
     * Adds the packages to the `require` of the `composer.json` written, `php` first and the others sorted.
     *
     * @param array<string, string> $constraints
     */
    private function require(string $file, array $constraints): void
    {
        /** @var array{require: array<string, string>} $composer the composer.json the templates wrote */
        $composer = json_decode(Files::read($file), true, flags: \JSON_THROW_ON_ERROR);
        $require = [...$composer['require'], ...$constraints];
        $php = ['php' => $require['php']];
        unset($require['php']);
        ksort($require);
        $composer['require'] = [...$php, ...$require];

        file_put_contents($file, json_encode($composer, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * What a planned package adds to the templates, or empty values.
     *
     * @return array<string, string>
     */
    private function plannedValues(PackageName $name, bool $planned, string $description, string $descriptionFr): array
    {
        $summary = $planned ? $this->firstSentence($description) : $description;
        $summaryFr = $planned ? $this->firstSentence($descriptionFr) : $descriptionFr;
        $values = [
            '{{summary}}' => $summary,
            '{{summary_fr}}' => $summaryFr,
            '{{summary_json}}' => substr(json_encode($summary, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), 1, -1),
            '{{namespace_php}}' => str_replace('\\', '\\\\', $name->namespace()),
            '{{planned}}' => '',
            '{{planned_fr}}' => '',
            '{{planned_status}}' => '',
            '{{planned_status_fr}}' => '',
            '{{changelog_planned_first}}' => '',
            '{{status}}' => '',
        ];

        if (!$planned) {
            return $values;
        }

        return [
            ...$values,
            '{{planned}}' => "\n\n**Being written.** The package holds its skeleton; the code described below is not written yet.\n\n".$description,
            '{{planned_fr}}' => "\n\n**En cours d'écriture.** Le paquet contient son squelette ; le code décrit ci-dessous n'est pas encore écrit.\n\n".$descriptionFr,
            '{{planned_status}}' => "\n| The code described above | Being written |",
            '{{planned_status_fr}}' => "\n| Le code décrit plus haut | En cours d'écriture |",
            '{{changelog_planned_first}}' => "\n- The description of what the package will do, in the README, while its code is being written.",
            '{{status}}' => "    // Being written: the gates leave it out when asked to (--skip-planned, PHIL_DEV_SKIP_PLANNED=1).\n    'status' => 'planned',\n",
            ...($name->bundle ? [] : [
                '{{reference}}' => '| — | The package is being written; it has no public type yet. |',
                '{{reference_fr}}' => "| — | Le paquet est en cours d'écriture ; il n'a pas encore de type public. |",
            ]),
        ];
    }

    /**
     * What the packages to require add to the configuration of composer-dependency-analyser, or empty values.
     *
     * @param list<string> $packages
     *
     * @return array<string, string>
     */
    private function requireValues(array $packages): array
    {
        if ([] === $packages) {
            return ['{{analyser_use}}' => '', '{{analyser_ignores}}' => ''];
        }

        return [
            '{{analyser_use}}' => "\nuse ShipMonk\\ComposerDependencyAnalyser\\Config\\ErrorType;",
            '{{analyser_ignores}}' => "\n    // The packages the code described in the README will use, required before it uses them.\n    ->ignoreErrorsOnPackages(['".implode("', '", $packages)."'], [ErrorType::UNUSED_DEPENDENCY])",
        ];
    }

    /**
     * The first sentence of a description: up to the first full stop, question or exclamation mark followed by a space.
     */
    private function firstSentence(string $description): string
    {
        return preg_split('/(?<=[.!?])\s+/', trim($description))[0] ?? '';
    }

    /**
     * @return array<string, string>
     */
    private function values(PackageName $name, string $description, bool $libraryExists): array
    {
        $namespace = $name->namespace();
        $matrix = Matrix::load();
        $php = $matrix->floor('php')->version;
        $symfony = $matrix->floor('symfony')->version;

        $registration = "```php\n".$namespace.'\\'.$name->bundleClass()."::class => ['all' => true],\n```";

        $installation = $name->bundle
            ? "Then register the bundle in `config/bundles.php`:\n\n".$registration."\n\nThe package needs PHP ".$php.' or later and Symfony '.$symfony.' or later.'
            : 'The package needs PHP '.$php.' or later.';

        $installationFr = $name->bundle
            ? "Enregistrez ensuite le bundle dans `config/bundles.php` :\n\n".$registration."\n\nLe paquet exige PHP ".$php.' ou une version plus récente, et Symfony '.$symfony.' ou une version plus récente.'
            : 'Le paquet exige PHP '.$php.' ou une version plus récente.';

        $extensionPoints = $name->bundle
            ? "| Replace a service | Alias the interface the bundle registers to your own service |\n| Add to the bundle | Tag a service with a tag this page lists; the bundle collects every service so tagged |"
            : '| Change a behaviour | Implement the interface the package takes, and pass your implementation |';

        $extensionPointsFr = $name->bundle
            ? "| Remplacer un service | Créez un alias de l'interface que le bundle enregistre vers votre propre service |\n| Ajouter au bundle | Étiquetez un service avec une étiquette que cette page énumère ; le bundle recueille tous les services ainsi étiquetés |"
            : "| Changer un comportement | Implémentez l'interface que le paquet reçoit, et passez-lui votre implémentation |";

        $reference = $name->bundle
            ? '| `'.$namespace.'\\'.$name->bundleClass().'` | The bundle: its configuration `'.$name->alias().'` and its services. |'
            : '| — | The package has no public type yet. |';

        $referenceFr = $name->bundle
            ? '| `'.$namespace.'\\'.$name->bundleClass().'` | Le bundle : sa configuration `'.$name->alias().'` et ses services. |'
            : "| — | Le paquet n'a pas encore de type public. |";

        $intro = $name->bundle && $libraryExists
            ? $name->package().' integrates `'.$name->library().'` with Symfony. The library alone suits an application without Symfony.'
            : $name->package().' is a Symfony bundle.';

        $introFr = $name->bundle && $libraryExists
            ? $name->package().' intègre `'.$name->library().'` à Symfony. La bibliothèque seule convient à une application sans Symfony.'
            : $name->package().' est un bundle Symfony.';

        return [
            '{{package}}' => $name->package(),
            '{{description}}' => $description,
            '{{namespace}}' => $namespace,
            '{{namespace_json}}' => str_replace('\\', '\\\\', $namespace),
            '{{bundle_class}}' => $name->bundleClass(),
            '{{alias}}' => $name->alias(),
            '{{library}}' => $name->library(),
            '{{intro}}' => $intro,
            '{{intro_fr}}' => $introFr,
            '{{year}}' => $this->year,
            '{{php}}' => $php,
            '{{symfony}}' => $symfony,
            '{{holder}}' => self::HOLDERS[$name->vendor],
            '{{contributing_intro}}' => self::INTRODUCTIONS[$name->vendor],
            '{{installation}}' => $installation,
            '{{installation_fr}}' => $installationFr,
            '{{extension_points}}' => $extensionPoints,
            '{{extension_points_fr}}' => $extensionPointsFr,
            '{{reference}}' => $reference,
            '{{reference_fr}}' => $referenceFr,
        ];
    }
}
