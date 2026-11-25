<?php

declare(strict_types=1);

namespace Phil\DevTools\PhpStan\Rules;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * The assignments among the statements of a loop, down to nested blocks, but not into a nested loop, which is looked
 * at on its own, nor into a closure or a function, which the loop does not run.
 */
final class LoopAssignmentFinder extends NodeVisitorAbstract
{
    /** @var list<Assign> */
    private array $found = [];

    /**
     * @param array<Node> $statements
     *
     * @return list<Assign>
     */
    public static function in(array $statements): array
    {
        $finder = new self();
        $traverser = new NodeTraverser($finder);
        $traverser->traverse($statements);

        return $finder->found;
    }

    #[Override]
    public function enterNode(Node $node): ?int
    {
        if ($node instanceof FunctionLike || $node instanceof For_ || $node instanceof Foreach_ || $node instanceof While_ || $node instanceof Do_) {
            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Assign) {
            $this->found[] = $node;
        }

        return null;
    }
}
