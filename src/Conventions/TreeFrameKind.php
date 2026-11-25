<?php

declare(strict_types=1);

namespace Phil\DevTools\Conventions;

/**
 * What an open builder of a configuration tree is.
 */
enum TreeFrameKind
{
    /** A node that holds a value of its own: a scalar, boolean, integer, float, enum or string node. */
    case Leaf;

    /** A node that holds others: an array, variable or generic node. */
    case Node;

    /** The prototype of a list or a map, whose options belong to each entry. */
    case Prototype;

    /** Another builder closed by `->end()`: `children()`, `validate()`, `beforeNormalization()`. */
    case Builder;
}
