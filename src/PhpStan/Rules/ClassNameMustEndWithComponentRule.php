<?php

declare(strict_types=1);

namespace Phil\DevTools\PhpStan\Rules;

use Override;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A class carrying `#[AsTwigComponent]` or `#[AsLiveComponent]` has a name that ends with `Component`, the way an
 * interface ends with `Interface`: the suffix tells the role of the class at a glance, and keeps its name clear of the
 * words PHP reserves, such as `List`.
 *
 * The attribute is compared by its resolved name and never loaded: a project without Symfony UX runs this rule as any
 * other, on a class that may carry an attribute of an optional component.
 *
 * @implements Rule<Class_>
 */
final class ClassNameMustEndWithComponentRule implements Rule
{
    public const string IDENTIFIER = 'phil.componentClassName';

    private const string SUFFIX = 'Component';

    /**
     * The attributes of a component, by lowercase name since PHP resolves a class name without regard to case.
     */
    private const array ATTRIBUTES = [
        'symfony\ux\twigcomponent\attribute\astwigcomponent' => 'AsTwigComponent',
        'symfony\ux\livecomponent\attribute\aslivecomponent' => 'AsLiveComponent',
    ];

    #[Override]
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $name = $node->name;

        // PHPStan hands an anonymous class over under a generated name, which nobody writes or reads.
        if (!$name instanceof Identifier || true === $node->getAttribute('anonymousClass') || str_ends_with($name->name, self::SUFFIX)) {
            return [];
        }

        $attribute = $this->componentAttribute($node);

        if (null === $attribute) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf('Class %s carries #[%s]: its name must end with "%s", such as %s.', $name->name, $attribute, self::SUFFIX, $name->name.self::SUFFIX))
                ->identifier(self::IDENTIFIER)
                ->line($name->getStartLine())
                ->tip('The suffix tells the role of the class at a glance, and keeps its name clear of the words PHP reserves, such as List.')
                ->build(),
        ];
    }

    /**
     * The short name of the first component attribute the class carries, if any.
     */
    private function componentAttribute(Class_ $class): ?string
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $found = self::ATTRIBUTES[strtolower($attribute->name->toString())] ?? null;

                if (null !== $found) {
                    return $found;
                }
            }
        }

        return null;
    }
}
