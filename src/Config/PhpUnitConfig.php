<?php

declare(strict_types=1);

namespace Phil\DevTools\Config;

use DOMDocument;
use DOMElement;
use Phil\DevTools\Exception\InvalidManifest;
use Phil\DevTools\Workspace\Package;
use Phil\DevTools\Workspace\Workspace;

/**
 * The root's PHPUnit configuration without the planned packages a workspace skips, for `phil-dev test` and Infection.
 *
 * The suites of a monorepo find every package by their globs, so leaving packages out takes a configuration of its
 * own: a copy of the root's, generated under `var/phil-dev/`, each relative path made absolute, each skipped package
 * excluded from every suite and from the source.
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

    private const string SCHEMA_NAMESPACE = 'http://www.w3.org/2001/XMLSchema-instance';

    public function __construct(private Workspace $workspace)
    {
    }

    /**
     * The configuration without the skipped packages; null when no package is skipped or the root has no configuration.
     */
    public function withoutSkipped(): ?string
    {
        $skipped = $this->workspace->skipped();
        $file = $this->source();

        if ([] === $skipped || null === $file) {
            return null;
        }

        $document = new DOMDocument();

        if (!$document->load($file, \LIBXML_NOERROR | \LIBXML_NOWARNING)) {
            throw new InvalidManifest(\sprintf('%s is not valid XML.', $file));
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
                $element->setAttributeNS(self::SCHEMA_NAMESPACE, 'xsi:noNamespaceSchemaLocation', $this->absolute($element->getAttributeNS(self::SCHEMA_NAMESPACE, 'noNamespaceSchemaLocation')));
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

    private function absolute(string $path): string
    {
        return str_starts_with($path, '/') ? $path : $this->workspace->root->path($path);
    }
}
