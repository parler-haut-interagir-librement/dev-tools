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
     * @param string $year the year of the copyright, such as "2026"
     */
    public function __construct(private string $year)
    {
    }

    /**
     * @return list<string> the files written, relative to the directory
     */
    public function write(PackageName $name, string $directory, string $description, bool $libraryExists = false): array
    {
        if (is_dir($directory) && [] !== array_diff((array) scandir($directory), ['.', '..'])) {
            throw new InvalidPackageName(\sprintf('%s already exists and is not empty.', $directory));
        }

        $values = $this->values($name, $description, $libraryExists);
        $written = [];

        foreach (['common', $name->bundle ? 'bundle' : 'library'] as $set) {
            $root = DevTools::resource('skeleton/'.$set);
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $relative = substr($file->getPathname(), \strlen($root) + 1);
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
        }

        sort($written);

        foreach (array_filter($written, Mirrors::isMirror(...)) as $mirror) {
            $source = Files::read($directory.'/'.Mirrors::sourceOf($mirror));
            file_put_contents($directory.'/'.$mirror, SourceStamp::apply(Files::read($directory.'/'.$mirror), $source));
        }

        return $written;
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
