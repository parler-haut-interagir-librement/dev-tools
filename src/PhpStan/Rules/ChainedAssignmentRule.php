<?php

declare(strict_types=1);

namespace Phil\DevTools\PhpStan\Rules;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\TypeCombinator;

/**
 * A chained assignment, `$a = $b = …`, neither shares a new object nor passes on what a set hook stored.
 *
 * `$a = $b = new Cart()` gives `$a` and `$b` one object: a change through one is seen through the other, which the line
 * does not say. `$a = $order->total = $amount`, when `total` has a `set` hook, gives `$a` the value written, not the
 * value the hook stored: an assignment is worth its right-hand side. The rule reports the outer assignment of such a
 * chain; one assignment per target, each with its own `new`, or a read of the property after it is written, says what
 * is meant.
 *
 * @implements Rule<Assign>
 */
final class ChainedAssignmentRule implements Rule
{
    public const string IDENTIFIER = 'phil.chainedAssignment';

    /**
     * The attribute that marks an assignment of a chain already reported.
     */
    private const string REPORTED = 'philChainedReported';

    #[Override]
    public function getNodeType(): string
    {
        return Assign::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->expr instanceof Assign) {
            return [];
        }

        // PHPStan visits the right-hand side of an assignment before the assignment: once a part of a chain is
        // reported, the assignments around it are not reported again.
        if (true === $node->expr->getAttribute(self::REPORTED)) {
            $node->setAttribute(self::REPORTED, true);

            return [];
        }

        $value = $node->expr;
        $hooked = null;

        while ($value instanceof Assign) {
            $hooked ??= $this->hookedProperty($value->var, $scope);
            $value = $value->expr;
        }

        if ($value instanceof New_ || null !== $hooked) {
            $node->setAttribute(self::REPORTED, true);
        }

        if ($value instanceof New_) {
            return [
                RuleErrorBuilder::message('A chained assignment gives one new object to each of its targets: assign each target its own new, or assign the variable to the others on lines of their own.')
                    ->identifier(self::IDENTIFIER)
                    ->tip('Every target holds the same object, and a change through one is seen through the others.')
                    ->build(),
            ];
        }

        if (null !== $hooked) {
            return [
                RuleErrorBuilder::message(\sprintf('A chained assignment passes on the value written to %s, not the value its set hook stored: write the property, then read it on a line of its own.', $hooked))
                    ->identifier(self::IDENTIFIER)
                    ->tip('An assignment is worth its right-hand side: the set hook of the property never reaches the other targets.')
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * The property the target names, as `Class::$name`, when it has a set hook.
     */
    private function hookedProperty(Expr $target, Scope $scope): ?string
    {
        if ((!$target instanceof PropertyFetch && !$target instanceof NullsafePropertyFetch) || !$target->name instanceof Identifier) {
            return null;
        }

        $name = $target->name->toString();
        $type = TypeCombinator::removeNull($scope->getType($target->var));

        foreach ($type->getObjectClassReflections() as $class) {
            if ($class->hasNativeProperty($name) && $class->getNativeProperty($name)->hasHook('set')) {
                return $class->getDisplayName().'::$'.$name;
            }
        }

        return null;
    }
}
