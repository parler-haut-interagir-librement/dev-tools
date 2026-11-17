<?php

declare(strict_types=1);

namespace Phil\DevTools\Config;

use DOMDocument;
use DOMElement;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\Workspace;

/**
 * The root's PHPUnit configuration with explicit directories and without the planned packages a workspace skips, for
 * `phil-dev test` and Infection.
 *
 * The suites of a monorepo find every package by their globs. The generated configuration is a copy of the root's,
 * under `var/phil-dev/`, each relative path made absolute and each glob replaced by the directories it matches when
 * the configuration is generated, sorted: PHPUnit and Infection read a fixed list of directories, whatever the
 * checkout's path, and a package added later is found by the next generation. The schema stays a path relative to
 * the generated file, or a URL as written: Infection resolves a schema path that is not a URL against the directory
 * of the configuration, absolute or not. A skipped package is left out of the
 * directories and excluded from every suite and from the source. A `**` pattern is left for PHPUnit to expand.
 */
final readonly class PhpUnitConfig
{
    /**
     * The root's configuration, the first of these that exists, as PHPUnit looks for it.
     *
     * @var list<string>
     */
    public const array FILES = ['phpunit.xml', 'phpunit.dist.xml', 'phpunit.xml.dist'];

    /**
     * The attributes that hold a path, relative to the configuration.
     *
     * @var list<string>
     */
    private const array PATH_ATTRIBUTES = ['bootstrap', 'cacheDirectory', 'cacheResultFile', 'outputFile', 'outputDirectory'];

    /**
     * The elements whose text is a path, relative to the configuration.
     *
     * @var list<string>
     */
    private const array PATH_ELEMENTS = ['directory', 'file', 'exclude'];

    /**
     * Where the generated configuration is written, under the root.
     */
    public const string GENERATED = 'var/phil-dev/phpunit/phpunit.xml';

    private const string SCHEMA_NAMESPACE = 'http://www.w3.org/2001/XMLSchema-instance';

    public function __construct(private Workspace $workspace)
    {
    }

    /**
     * The configuration with explicit directories and without the skipped packages; null when no package is skipped
     * and no path holds a glob, or when the root has no configuration.
     */
    public function generated(): ?string
    {
        $skipped = $this->workspace->skipped();
        $file = $this->source();

        if (null === $file) {
            return null;
        }

        $document = new DOMDocument();

        if (!$document->load($file, \LIBXML_NOERROR | \LIBXML_NOWARNING)) {
            throw new InvalidManifest(\sprintf('%s is not valid XML.', $file));
        }

        $expanded = $this->expand($document, $skipped);

        if ([] === $skipped && !$expanded) {
            return null;
        }

        $this->absolutize($document);
        $this->exclude($document, $skipped);

        return (string) $document->saveXML();
    }

    /**
     * @return non-empty-string|null
     */
    private function source(): ?string
    {
        foreach (self::FILES as $name) {
            $file = $this->workspace->root->dir.'/'.$name;

            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Replaces each path element whose text is a glob by one element per path it matches, in the order glob() sorts them, outside the skipped
     * packages; an element that matches nothing is removed.
     *
     * @param list<Package> $skipped
     *
     * @return bool whether a glob was found
     */
    private function expand(DOMDocument $document, array $skipped): bool
    {
        $found = false;

        /** @var list<DOMElement> $elements */
        $elements = iterator_to_array($document->getElementsByTagName('*'), false);

        foreach ($elements as $element) {
            $path = trim($element->textContent);

            if (!\in_array($element->localName, self::PATH_ELEMENTS, true) || null !== $element->firstElementChild || !$this->isGlob($path)) {
                continue;
            }

            $found = true;

            foreach ($this->matches($this->absolute($path), 'directory' === $element->localName, $skipped) as $match) {
                $copy = match ($element->localName) {
                    'directory' => $document->createElement('directory'),
                    'file' => $document->createElement('file'),
                    default => $document->createElement('exclude'),
                };
                $copy->textContent = $match;
                $element->before($copy);

                foreach ($element->attributes as $attribute) {
                    $copy->setAttribute($attribute->name, $attribute->value);
                }
            }

            $element->remove();
        }

        return $found;
    }

    /**
     * A pattern PHP's glob expands; a `**` pattern is PHPUnit's own and stays as written.
     */
    private function isGlob(string $path): bool
    {
        return false !== strpbrk($path, '*?[') && !str_contains($path, '**');
    }

    /**
     * @param list<Package> $skipped
     *
     * @return list<string>
     */
    private function matches(string $pattern, bool $directoriesOnly, array $skipped): array
    {
        $matches = [];

        $found = glob($pattern, $directoriesOnly ? \GLOB_ONLYDIR : 0);

        foreach (false === $found ? [] : $found as $match) {
            foreach ($skipped as $package) {
                if (str_starts_with($match.'/', $package->dir.'/')) {
                    continue 2;
                }
            }

            $matches[] = $match;
        }

        return $matches;
    }

    private function absolutize(DOMDocument $document): void
    {
        /** @var DOMElement $element */
        foreach ($document->getElementsByTagName('*') as $element) {
            foreach (self::PATH_ATTRIBUTES as $attribute) {
                if ($element->hasAttribute($attribute)) {
                    $element->setAttribute($attribute, $this->absolute($element->getAttribute($attribute)));
                }
            }

            if ($element->hasAttributeNS(self::SCHEMA_NAMESPACE, 'noNamespaceSchemaLocation')) {
                $element->setAttributeNS(self::SCHEMA_NAMESPACE, 'xsi:noNamespaceSchemaLocation', $this->schema($element->getAttributeNS(self::SCHEMA_NAMESPACE, 'noNamespaceSchemaLocation')));
            }

            if (\in_array($element->localName, self::PATH_ELEMENTS, true) && null === $element->firstElementChild) {
                $element->textContent = $this->absolute(trim($element->textContent));
            }
        }
    }

    /**
     * @param list<Package> $skipped
     */
    private function exclude(DOMDocument $document, array $skipped): void
    {
        if ([] === $skipped) {
            return;
        }

        /** @var DOMElement $suite */
        foreach ($document->getElementsByTagName('testsuite') as $suite) {
            foreach ($skipped as $package) {
                $suite->appendChild($document->createElement('exclude', $package->dir));
            }
        }

        /** @var DOMElement $source */
        foreach ($document->getElementsByTagName('source') as $source) {
            $exclude = null;

            foreach ($source->childNodes as $child) {
                if ($child instanceof DOMElement && 'exclude' === $child->localName) {
                    $exclude = $child;
                }
            }

            if (!$exclude instanceof DOMElement) {
                $exclude = $document->createElement('exclude');
                $source->appendChild($exclude);
            }

            foreach ($skipped as $package) {
                $exclude->appendChild($document->createElement('directory', $package->dir));
            }
        }
    }

    /**
     * The schema's location as the generated file reaches it: a URL as written, a path relative to the generated
     * file's directory otherwise.
     */
    private function schema(string $location): string
    {
        if (str_contains($location, '://')) {
            return $location;
        }

        $from = self::segments(\dirname($this->workspace->root->path(self::GENERATED)));
        $to = self::segments($this->absolute($location));

        while (isset($from[0], $to[0]) && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('../', \count($from)).implode('/', $to);
    }

    /**
     * The segments of an absolute path, without `.` and with each `..` taking back the segment before it.
     *
     * @return list<string>
     */
    private static function segments(string $path): array
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ('..' === $segment) {
                array_pop($segments);
            } elseif ('' !== $segment && '.' !== $segment) {
                $segments[] = $segment;
            }
        }

        return $segments;
    }

    private function absolute(string $path): string
    {
        return str_starts_with($path, '/') ? $path : $this->workspace->root->path($path);
    }
}
