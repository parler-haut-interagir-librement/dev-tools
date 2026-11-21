<?php

declare(strict_types=1);

namespace Phil\DevTools\PhpStan\Rules;

use Override;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Psr\Log\LoggerInterface;

/**
 * A call to a PSR-3 logger passes a constant message with placeholders, and a context that holds no object other than a
 * `Throwable`.
 *
 * A message built by interpolation, concatenation or `sprintf()` is a new message for every value: a handler can no
 * longer group the records of one event, and the value is written where a processor that masks or drops a context key
 * never sees it. An object in the context is serialised however each handler sees fit, with whatever it holds,
 * personal data included; PSR-3 names the `Throwable` under `exception` as the one object a context carries.
 *
 * The logger is recognised by its type, `Psr\Log\LoggerInterface` or a class that implements it, compared by name: a
 * project without `psr/log` runs this rule as any other. A message whose value PHPStan knows to be a constant string,
 * such as two literals joined to keep a line short, is accepted.
 *
 * A nullsafe call, `$logger?->info()`, reaches the rule as a method call on the logger without `null`.
 *
 * @implements Rule<MethodCall>
 */
final class PsrLogContextRule implements Rule
{
    public const string MESSAGE_IDENTIFIER = 'phil.psrLogMessage';

    public const string CONTEXT_IDENTIFIER = 'phil.psrLogContext';

    private const string LOGGER = LoggerInterface::class;

    /**
     * How deep the rule looks into the arrays of a context.
     */
    private const int DEPTH = 4;

    /**
     * The methods of the logger, by lowercase name, with the position of their message.
     */
    private const array METHODS = [
        'emergency' => 0,
        'alert' => 0,
        'critical' => 0,
        'error' => 0,
        'warning' => 0,
        'notice' => 0,
        'info' => 0,
        'debug' => 0,
        'log' => 1,
    ];

    /**
     * The functions that format a message, by lowercase name.
     */
    private const array FORMATTERS = ['sprintf' => true, 'vsprintf' => true];

    #[Override]
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier || $node->isFirstClassCallable()) {
            return [];
        }

        $method = strtolower($node->name->name);
        $position = self::METHODS[$method] ?? null;

        if (null === $position || !$this->isLogger($scope->getType($node->var))) {
            return [];
        }

        $call = 'LoggerInterface::'.$method.'()';
        $errors = [];
        $message = $this->argument($node->getArgs(), 'message', $position);

        if ($message instanceof Expr) {
            $errors = $this->checkMessage($message, $scope, $call);
        }

        $context = $this->argument($node->getArgs(), 'context', $position + 1);

        if ($context instanceof Expr) {
            foreach ($this->objectsIn($scope->getType($context), [], 0) as [$path, $class]) {
                $errors[] = RuleErrorBuilder::message(\sprintf('The context of %s holds an object of class %s%s: only a Throwable, under "exception", may be an object.', $call, $class, [] === $path ? '' : ' under '.implode(' > ', $path)))
                    ->identifier(self::CONTEXT_IDENTIFIER)
                    ->line($context->getStartLine())
                    ->tip('Pass a scalar, such as an identifier, or an array of scalars: each handler serialises the context its own way, and may keep or send it.')
                    ->build();
            }
        }

        return $errors;
    }

    private function isLogger(Type $type): bool
    {
        return new ObjectType(self::LOGGER)->isSuperTypeOf(TypeCombinator::removeNull($type))->yes();
    }

    /**
     * The expression of an argument, given by its name or its position, or null when it is missing or unpacked.
     *
     * @param array<Arg> $arguments
     */
    private function argument(array $arguments, string $name, int $position): ?Expr
    {
        $positional = 0;

        foreach ($arguments as $argument) {
            if ($argument->unpack) {
                return null;
            }

            if (null !== $argument->name) {
                if ($name === $argument->name->name) {
                    return $argument->value;
                }

                continue;
            }

            if ($position === $positional) {
                return $argument->value;
            }

            ++$positional;
        }

        return null;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function checkMessage(Expr $message, Scope $scope, string $call): array
    {
        $how = match (true) {
            $message instanceof InterpolatedString => 'interpolation',
            $message instanceof Concat => 'concatenation',
            $this->isFormatter($message) => 'formatting',
            default => null,
        };

        if (null === $how) {
            return [];
        }

        // Every value PHPStan knows it may take is a literal: a finite set of messages, each one constant.
        if ([] !== $scope->getType($message)->getConstantStrings()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf('The message of %s is built by %s: write a constant message with placeholders, such as "Order {order} shipped.", and pass the values in the context.', $call, $how))
                ->identifier(self::MESSAGE_IDENTIFIER)
                ->line($message->getStartLine())
                ->tip('A constant message lets a handler group the records of one event, and keeps each value in the context, where a processor can mask or drop it.')
                ->build(),
        ];
    }

    private function isFormatter(Expr $expression): bool
    {
        return $expression instanceof FuncCall
            && $expression->name instanceof Name
            && isset(self::FORMATTERS[$expression->name->toLowerString()]);
    }

    /**
     * The objects other than a Throwable a context may hold, each with the keys that lead to it.
     *
     * @param list<string> $path
     *
     * @return list<array{list<string>, string}>
     */
    private function objectsIn(Type $type, array $path, int $depth): array
    {
        $found = [];
        $throwable = new ObjectType('Throwable');

        foreach ($type->getObjectClassNames() as $class) {
            if (!$throwable->isSuperTypeOf(new ObjectType($class))->yes()) {
                $found[] = [$path, $class];
            }
        }

        if ([] === $type->getObjectClassNames() && $type->isObject()->yes()) {
            $found[] = [$path, 'object'];
        }

        if ($depth >= self::DEPTH) {
            return $found;
        }

        foreach ($type->getConstantArrays() as $array) {
            foreach ($array->getKeyTypes() as $index => $key) {
                $found = [...$found, ...$this->objectsIn($array->getValueTypes()[$index], [...$path, '"'.$key->getValue().'"'], $depth + 1)];
            }
        }

        if ([] === $type->getConstantArrays()) {
            foreach ($type->getArrays() as $array) {
                $found = [...$found, ...$this->objectsIn($array->getIterableValueType(), $path, $depth + 1)];
            }
        }

        return $found;
    }
}
