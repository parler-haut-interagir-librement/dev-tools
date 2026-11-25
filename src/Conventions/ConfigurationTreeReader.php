<?php

declare(strict_types=1);

namespace Phil\DevTools\Conventions;

use PhpToken;

/**
 * Reads the options of a configuration tree from the tokens of a PHP file, without loading it: the chains of
 * `->xxxNode('name')`, `->children()`, prototypes, `->validate()` and `->beforeNormalization()` up to their `->end()`.
 * A statement that ends closes what it left open, and a node built in a helper that receives a builder is read at the
 * level of the helper's builder.
 */
final class ConfigurationTreeReader
{
    /**
     * The nodes that hold a value of their own, by lowercase method name.
     */
    private const array LEAVES = [
        'scalarnode' => true,
        'booleannode' => true,
        'integernode' => true,
        'floatnode' => true,
        'enumnode' => true,
        'stringnode' => true,
    ];

    private const array NODES = ['arraynode' => true, 'variablenode' => true, 'node' => true];

    private const array BUILDERS = ['children' => true, 'validate' => true, 'beforenormalization' => true];

    /**
     * The calls that give a node its default, with the value they give, or null for one not read.
     */
    private const array DEFAULTS = [
        'defaulttrue' => 'true',
        'defaultfalse' => 'false',
        'defaultnull' => 'null',
        'canbeenabled' => null,
        'canbedisabled' => null,
    ];

    /** @var list<TreeFrame> */
    private array $frames = [];

    /** @var list<ConfigurationOption> */
    private array $options = [];

    /**
     * @return list<ConfigurationOption>
     */
    public function options(string $code): array
    {
        $tokens = array_values(array_filter(PhpToken::tokenize($code), static fn (PhpToken $token): bool => !$token->isIgnorable()));
        $this->frames = [];
        $this->options = [];
        $depth = 0;

        foreach ($tokens as $i => $token) {
            $depth += match ($token->text) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if (0 === $depth && (';' === $token->text || '}' === $token->text)) {
                $this->closeAll();
            }

            if ($token->is([\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR]) && '(' === ($tokens[$i + 2]->text ?? null)) {
                $this->call(strtolower($tokens[$i + 1]->text), $tokens, $i + 3);
            }
        }

        $this->closeAll();

        return $this->options;
    }

    /**
     * A method call of the chain, whose arguments start at `$argument`.
     *
     * @param list<PhpToken> $tokens
     */
    private function call(string $method, array $tokens, int $argument): void
    {
        if (isset(self::LEAVES[$method]) || isset(self::NODES[$method])) {
            $name = true === ($tokens[$argument] ?? null)?->is(\T_CONSTANT_ENCAPSED_STRING) ? RecipeReader::normalize($tokens[$argument]->text) : '*';
            $this->frames[] = new TreeFrame(isset(self::LEAVES[$method]) ? TreeFrameKind::Leaf : TreeFrameKind::Node, $name);

            return;
        }

        if (isset(self::BUILDERS[$method]) || str_ends_with($method, 'prototype')) {
            $this->frames[] = new TreeFrame(isset(self::BUILDERS[$method]) ? TreeFrameKind::Builder : TreeFrameKind::Prototype);

            return;
        }

        $frame = $this->frames[\count($this->frames) - 1] ?? null;

        if (!$frame instanceof TreeFrame) {
            return;
        }

        if ('end' === $method) {
            $this->close();
        } elseif ('defaultvalue' === $method) {
            $frame->hasDefault = true;
            $frame->default = $this->literal($tokens, $argument);
        } elseif (\array_key_exists($method, self::DEFAULTS)) {
            $frame->hasDefault = true;
            $frame->default = self::DEFAULTS[$method];
        } elseif ('isrequired' === $method) {
            $frame->required = true;
        }
    }

    private function closeAll(): void
    {
        while ([] !== $this->frames) {
            $this->close();
        }
    }

    /**
     * Pops the frame on top; a leaf outside every prototype becomes an option.
     */
    private function close(): void
    {
        $frame = array_pop($this->frames);

        if (!$frame instanceof TreeFrame || TreeFrameKind::Leaf !== $frame->kind || '*' === $frame->name) {
            return;
        }

        $names = [];

        foreach ($this->frames as $parent) {
            if (TreeFrameKind::Prototype === $parent->kind || '*' === $parent->name) {
                return;
            }

            if (TreeFrameKind::Node === $parent->kind) {
                $names[] = $parent->name;
            }
        }

        $names[] = $frame->name;
        $this->options[] = new ConfigurationOption(implode('.', $names), $frame->hasDefault, $frame->default, $frame->required);
    }

    /**
     * The value of a literal argument, alone between its parentheses, normalised; null for anything else.
     *
     * @param list<PhpToken> $tokens
     */
    private function literal(array $tokens, int $start): ?string
    {
        $token = $tokens[$start] ?? null;

        if (!$token instanceof PhpToken || ')' !== ($tokens[$start + 1]->text ?? null)) {
            return null;
        }

        if ($token->is([\T_CONSTANT_ENCAPSED_STRING, \T_LNUMBER, \T_DNUMBER])) {
            return RecipeReader::normalize($token->text);
        }

        return $token->is(\T_STRING) && \in_array(strtolower($token->text), ['true', 'false', 'null'], true) ? strtolower($token->text) : null;
    }
}
