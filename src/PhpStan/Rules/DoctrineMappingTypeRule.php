<?php

declare(strict_types=1);

namespace Phil\DevTools\PhpStan\Rules;

use Override;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * The Doctrine mapping of a package keeps clear of the two phantom types: the mutable date types, and `decimal`.
 *
 * Doctrine tells a change by comparing the value it loaded with the value it finds: a `\DateTime` changed in place is
 * the same object, so the change is never written — and an entity that shares one instance changes another behind its
 * back. A `decimal` column comes back as a string, which the first arithmetic turns into a float. The rule refuses a
 * `#[Column]` typed `date`, `datetime`, `datetimetz`, `time` (the `Types::*_MUTABLE` constants) or `decimal`
 * (`Types::DECIMAL`), and a mapped property whose native type is `\DateTime`: the immutable types and
 * `\DateTimeImmutable`, an integer of minor units or a string column, are the forms that hold.
 *
 * Attributes and type names are read as written and never loaded: a project without Doctrine runs the rule as any other.
 *
 * @implements Rule<InClassNode>
 */
final class DoctrineMappingTypeRule implements Rule
{
    public const string MUTABLE_DATE_IDENTIFIER = 'phil.doctrineMutableDate';

    public const string DECIMAL_IDENTIFIER = 'phil.doctrineDecimal';

    private const string MAPPING = 'doctrine\orm\mapping\\';

    private const string COLUMN = 'doctrine\orm\mapping\column';

    private const string TYPES = 'doctrine\dbal\types\types';

    /**
     * The classes a mapping attribute makes an entity, an embeddable or a mapped superclass, by lowercase name.
     */
    private const array MAPPED_CLASSES = [
        'doctrine\orm\mapping\entity',
        'doctrine\orm\mapping\embeddable',
        'doctrine\orm\mapping\mappedsuperclass',
    ];

    /**
     * The mutable date types, by their name in a column, and the immutable type to use instead.
     */
    private const array MUTABLE_DATES = [
        'date' => 'Types::DATE_IMMUTABLE',
        'datetime' => 'Types::DATETIME_IMMUTABLE',
        'datetimetz' => 'Types::DATETIMETZ_IMMUTABLE',
        'time' => 'Types::TIME_IMMUTABLE',
    ];

    /**
     * The constants of `Doctrine\DBAL\Types\Types` that name a refused type, and that type's name.
     */
    private const array CONSTANTS = [
        'DATE_MUTABLE' => 'date',
        'DATETIME_MUTABLE' => 'datetime',
        'DATETIMETZ_MUTABLE' => 'datetimetz',
        'TIME_MUTABLE' => 'time',
        'DECIMAL' => 'decimal',
    ];

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
        $class = $node->getOriginalNode();
        $mapped = $this->hasMapping($class->attrGroups, self::MAPPED_CLASSES);
        $errors = [];

        foreach ($this->properties($class) as [$name, $type, $groups, $line]) {
            $type = null === $type ? null : $this->mutableDateType($type);
            $column = $this->column($groups);
            $refused = $column instanceof Attribute ? $this->refusedType($column) : null;

            if ($column instanceof Attribute && null !== $refused) {
                $errors[] = $this->columnError($node->getClassReflection()->getDisplayName(), $name, $refused, $column->getStartLine());
            } elseif (null !== $type && ($mapped || $this->hasMapping($groups))) {
                $errors[] = RuleErrorBuilder::message(\sprintf('Property %s::$%s is mapped with the mutable type %s: type it \DateTimeImmutable, mapped as an immutable date type.', $node->getClassReflection()->getDisplayName(), $name, $type))
                    ->identifier(self::MUTABLE_DATE_IDENTIFIER)
                    ->line($line)
                    ->tip('Doctrine compares the object it loaded with the one it finds: a \DateTime changed in place is the same object, and the change is never written.')
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * The properties of a class, declared or promoted: their name, native type, attributes and line.
     *
     * @return list<array{string, Identifier|Name|ComplexType|null, array<AttributeGroup>, int}>
     */
    private function properties(ClassLike $class): array
    {
        $properties = [];

        foreach ($class->stmts as $statement) {
            if ($statement instanceof Property) {
                foreach ($statement->props as $property) {
                    $properties[] = [$property->name->toString(), $statement->type, $statement->attrGroups, $property->getStartLine()];
                }
            } elseif ($statement instanceof ClassMethod && '__construct' === $statement->name->toLowerString()) {
                foreach ($statement->params as $param) {
                    if ($this->isPromoted($param) && $param->var instanceof Variable && \is_string($param->var->name)) {
                        $properties[] = [$param->var->name, $param->type, $param->attrGroups, $param->getStartLine()];
                    }
                }
            }
        }

        return $properties;
    }

    private function isPromoted(Param $param): bool
    {
        return 0 !== $param->flags || [] !== $param->hooks;
    }

    /**
     * Whether one of the attributes is a Doctrine mapping attribute; among those given when a list is given.
     *
     * @param array<AttributeGroup> $groups
     * @param list<string>|null     $among
     */
    private function hasMapping(array $groups, ?array $among = null): bool
    {
        foreach ($groups as $group) {
            foreach ($group->attrs as $attribute) {
                $name = strtolower($attribute->name->toString());

                if (null === $among ? str_starts_with($name, self::MAPPING) : \in_array($name, $among, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<AttributeGroup> $groups
     */
    private function column(array $groups): ?Attribute
    {
        foreach ($groups as $group) {
            foreach ($group->attrs as $attribute) {
                if (self::COLUMN === strtolower($attribute->name->toString())) {
                    return $attribute;
                }
            }
        }

        return null;
    }

    /**
     * The refused type a column names, by its argument `type` or its second positional argument.
     */
    private function refusedType(Attribute $column): ?string
    {
        $type = null;

        foreach ($column->args as $position => $argument) {
            if ('type' === $argument->name?->toString() || (!$argument->name instanceof Identifier && 1 === $position)) {
                $type = $argument->value;
            }
        }

        if ($type instanceof String_) {
            $name = strtolower($type->value);

            return 'decimal' === $name || \array_key_exists($name, self::MUTABLE_DATES) ? $name : null;
        }

        if ($type instanceof ClassConstFetch && $type->class instanceof Name && $type->name instanceof Identifier && self::TYPES === strtolower($type->class->toString())) {
            $constant = self::CONSTANTS[$type->name->toString()] ?? null;

            return \is_string($constant) ? $constant : null;
        }

        return null;
    }

    private function columnError(string $class, string $property, string $type, int $line): IdentifierRuleError
    {
        if ('decimal' === $type) {
            return RuleErrorBuilder::message(\sprintf('Property %s::$%s is mapped as "decimal": keep an integer of minor units, or a string column the domain parses.', $class, $property))
                ->identifier(self::DECIMAL_IDENTIFIER)
                ->line($line)
                ->tip('A decimal column comes back as a string, which the first arithmetic turns into a float and its rounding.')
                ->build();
        }

        return RuleErrorBuilder::message(\sprintf('Property %s::$%s is mapped as the mutable type "%s": use %s and a \DateTimeImmutable.', $class, $property, $type, self::MUTABLE_DATES[$type] ?? 'an immutable date type'))
            ->identifier(self::MUTABLE_DATE_IDENTIFIER)
            ->line($line)
            ->tip('Doctrine compares the object it loaded with the one it finds: a \DateTime changed in place is the same object, and the change is never written.')
            ->build();
    }

    /**
     * `\DateTime` when the type is or holds it, else null.
     */
    private function mutableDateType(Identifier|Name|ComplexType $type): ?string
    {
        $names = match (true) {
            $type instanceof NullableType => [$type->type],
            $type instanceof UnionType, $type instanceof IntersectionType => $type->types,
            default => [$type],
        };

        foreach ($names as $name) {
            if ($name instanceof Name && 'datetime' === strtolower($name->toString())) {
                return '\DateTime';
            }

            if ($name instanceof IntersectionType && null !== $this->mutableDateType($name)) {
                return '\DateTime';
            }
        }

        return null;
    }
}
