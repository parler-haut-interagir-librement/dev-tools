<?php

declare(strict_types=1);

namespace Phil\DevTools\PhpStan\Rules;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A class does not declare again a property that a parent declares `private` or `static`.
 *
 * Neither is replaced: a `private` property declared again is a second property under the same name, which the
 * parent's methods never see, and a `static` one declared again is a second value, which `self::` in the parent and
 * `static::` in the child read apart. Each reads like one property and holds two. The rule reports the declaration in
 * the child, declared or promoted, with the parent that declares the first.
 *
 * @implements Rule<InClassNode>
 */
final class InheritedPropertyRedeclarationRule implements Rule
{
    public const string IDENTIFIER = 'phil.propertyRedeclaration';

    #[Override]
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        $parents = $class->getParents();

        if ([] === $parents) {
            return [];
        }

        $errors = [];

        foreach ($this->declared($node->getOriginalNode()->stmts) as $name => $line) {
            foreach ($parents as $parent) {
                if (!$parent->hasNativeProperty($name)) {
                    continue;
                }

                $property = $parent->getNativeProperty($name);

                if ($property->getDeclaringClass()->getName() !== $parent->getName()) {
                    continue;
                }

                if ($property->isPrivate() || $property->isStatic()) {
                    $errors[] = RuleErrorBuilder::message(\sprintf('Property %s::$%s declares again the %s property %s::$%s: the two are distinct, and the parent never sees this one.', $class->getDisplayName(), $name, $property->isStatic() ? 'static' : 'private', $parent->getDisplayName(), $name))
                        ->identifier(self::IDENTIFIER)
                        ->line($line)
                        ->tip("Rename the property, or make the parent's protected and drop this declaration.")
                        ->build();
                }

                break;
            }
        }

        return $errors;
    }

    /**
     * The properties a class declares itself, declared or promoted, with their line.
     *
     * @param array<Node\Stmt> $statements
     *
     * @return array<string, int>
     */
    private function declared(array $statements): array
    {
        $declared = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Property) {
                foreach ($statement->props as $property) {
                    $declared[$property->name->toString()] = $property->getStartLine();
                }
            } elseif ($statement instanceof ClassMethod && '__construct' === $statement->name->toLowerString()) {
                foreach ($statement->params as $param) {
                    if ((0 !== $param->flags || [] !== $param->hooks) && $param->var instanceof Variable && \is_string($param->var->name)) {
                        $declared[$param->var->name] = $param->getStartLine();
                    }
                }
            }
        }

        return $declared;
    }
}
