<?php

declare(strict_types=1);

namespace Phil\DevTools\PhpStan\Rules;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\While_;
use PhpParser\Node\VarLikeIdentifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * An array is not merged into itself at each turn of a loop: `$all = array_merge($all, $part)` copies the whole of
 * `$all` every time, and a loop of n parts costs n² copies.
 *
 * The parts are collected in a list and merged once, after the loop — `array_merge(...$parts)` —, or appended one by
 * one with `[]=`. The rule looks at the statements of `for`, `foreach`, `while` and `do`, down to the nested blocks
 * but not into a nested loop, which it looks at on its own, nor into a closure or a function, which the loop does not
 * run; it reports `array_merge()` and `array_merge_recursive()` whose result goes back to a variable or a property
 * passed among their arguments.
 *
 * @implements Rule<Stmt>
 */
final class ArrayMergeInLoopRule implements Rule
{
    public const string IDENTIFIER = 'phil.arrayMergeInLoop';

    private const array FUNCTIONS = ['array_merge' => true, 'array_merge_recursive' => true];

    #[Override]
    public function getNodeType(): string
    {
        return Stmt::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof For_ && !$node instanceof Foreach_ && !$node instanceof While_ && !$node instanceof Do_) {
            return [];
        }

        $errors = [];

        foreach (LoopAssignmentFinder::in($node->stmts) as $assign) {
            $target = $this->name($assign->var);
            $call = $assign->expr;

            if (null === $target || !$call instanceof FuncCall || !$call->name instanceof Name || $call->isFirstClassCallable()) {
                continue;
            }

            $function = strtolower($call->name->toString());

            if (!isset(self::FUNCTIONS[$function])) {
                continue;
            }

            foreach ($call->getArgs() as $arg) {
                if (!$arg->unpack && $this->name($arg->value) === $target) {
                    $errors[] = RuleErrorBuilder::message(\sprintf('%s() merges %s into itself at each turn of the loop: collect the parts in a list and merge them once after it, or append with []=.', $function, $target))
                        ->identifier(self::IDENTIFIER)
                        ->line($assign->getStartLine())
                        ->tip('Each turn copies the whole array: a loop of n parts makes n² copies.')
                        ->build();

                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * A variable or a property of `$this` or of a class, as written, or null for anything else.
     */
    private function name(Expr $expr): ?string
    {
        return match (true) {
            $expr instanceof Variable => \is_string($expr->name) ? '$'.$expr->name : null,
            $expr instanceof PropertyFetch => $expr->var instanceof Variable && \is_string($expr->var->name) && $expr->name instanceof Identifier ? '$'.$expr->var->name.'->'.$expr->name->toString() : null,
            $expr instanceof StaticPropertyFetch => $expr->class instanceof Name && $expr->name instanceof VarLikeIdentifier ? $expr->class->toString().'::$'.$expr->name->toString() : null,
            default => null,
        };
    }
}
