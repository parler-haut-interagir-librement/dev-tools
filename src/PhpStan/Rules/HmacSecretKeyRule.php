<?php

declare(strict_types=1);

namespace Phil\DevTools\PhpStan\Rules;

use Override;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VarLikeIdentifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * The application secret is never the key of an HMAC as it is: each use derives its own key from it, with `hash_hkdf()`
 * and a label of its own.
 *
 * Two bundles that key their digests with the same secret produce digests that match each other: a pseudonym made by
 * one recognises the person behind a pseudonym made by the other, and a signature made for one purpose is valid for
 * another. A key derived per use, `hash_hkdf('sha256', $secret, 32, 'phil_forms.email_digest')`, keeps them apart.
 *
 * The rule looks at the key of `hash_hmac()`, `hash_hmac_file()`, `hash_init()`, `sodium_crypto_auth()`,
 * `sodium_crypto_auth_verify()` and `sodium_crypto_generichash()`, positional or named, and reports it when it is the
 * application secret as given: the literal `%kernel.secret%`, `getParameter('kernel.secret')`, or a variable, a
 * property or an array key named `secret`, `kernelSecret`, `appSecret` or `applicationSecret`, cast or not — the names
 * under which a bundle receives `%kernel.secret%`. A key held under another name, such as a TOTP seed or the shared
 * secret of a webhook provider, is not the application's, and is named after what it is.
 *
 * @implements Rule<FuncCall>
 */
final class HmacSecretKeyRule implements Rule
{
    public const string IDENTIFIER = 'phil.hmacSecretKey';

    /**
     * The functions that take a key, by lowercase name, with the position of that key.
     */
    private const array FUNCTIONS = [
        'hash_hmac' => 2,
        'hash_hmac_file' => 2,
        'hash_init' => 2,
        'sodium_crypto_auth' => 1,
        'sodium_crypto_auth_verify' => 2,
        'sodium_crypto_generichash' => 1,
    ];

    /**
     * The names a bundle gives the application secret, lowercase.
     */
    private const array SECRET_NAMES = ['secret', 'kernelsecret', 'appsecret', 'applicationsecret', 'kernel.secret'];

    private const string PARAMETER = 'kernel.secret';

    #[Override]
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name || $node->isFirstClassCallable()) {
            return [];
        }

        $function = strtolower($node->name->toString());
        $position = self::FUNCTIONS[$function] ?? null;

        if (null === $position) {
            return [];
        }

        $key = $this->key($node->getArgs(), $position);
        $held = $key instanceof Expr ? $this->secret($key) : null;

        if (null === $held) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf('The key of %s() is the application secret as given (%s): derive a key for this use, such as hash_hkdf(\'sha256\', $secret, 32, \'phil_example.digest\').', $function, $held))
                ->identifier(self::IDENTIFIER)
                ->tip('One key per use, under a label of its own: a digest or a signature made for one use never matches one made for another.')
                ->build(),
        ];
    }

    /**
     * @param array<Arg> $args
     */
    private function key(array $args, int $position): ?Expr
    {
        foreach ($args as $index => $arg) {
            if ($arg->unpack) {
                return null;
            }

            if (null === $arg->name ? $index === $position : 'key' === $arg->name->toLowerString()) {
                return $arg->value;
            }
        }

        return null;
    }

    /**
     * How the key holds the application secret, as written, or null when it does not.
     */
    private function secret(Expr $key): ?string
    {
        if ($key instanceof Cast) {
            return $this->secret($key->expr);
        }

        if ($key instanceof String_) {
            return str_contains($key->value, '%'.self::PARAMETER.'%') ? "'%kernel.secret%'" : null;
        }

        $fetched = $this->fetched($key);

        if (null !== $fetched) {
            return $fetched;
        }

        if ($key instanceof ArrayDimFetch) {
            return $key->dim instanceof String_ && $this->isSecretName($key->dim->value) ? \sprintf("['%s']", $key->dim->value) : null;
        }

        if ($key instanceof MethodCall && !$key->isFirstClassCallable() && $key->name instanceof Identifier && 'getparameter' === $key->name->toLowerString()) {
            $argument = $key->getArgs()[0]->value ?? null;

            return $argument instanceof String_ && self::PARAMETER === $argument->value ? "getParameter('kernel.secret')" : null;
        }

        return null;
    }

    /**
     * How a variable or a property holds the application secret, as written, or null when it does not.
     */
    private function fetched(Expr $key): ?string
    {
        [$prefix, $name] = match (true) {
            $key instanceof Variable => ['$', $key->name],
            $key instanceof PropertyFetch, $key instanceof NullsafePropertyFetch => ['->', $key->name instanceof Identifier ? $key->name->toString() : null],
            $key instanceof StaticPropertyFetch => ['::$', $key->name instanceof VarLikeIdentifier ? $key->name->toString() : null],
            default => ['', null],
        };

        return \is_string($name) && $this->isSecretName($name) ? $prefix.$name : null;
    }

    private function isSecretName(string $name): bool
    {
        return \in_array(strtolower($name), self::SECRET_NAMES, true);
    }
}
