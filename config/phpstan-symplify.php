<?php

declare(strict_types=1);

use Symplify\PHPStanRules\Rules\CheckRequiredInterfaceInContractNamespaceRule;
use Symplify\PHPStanRules\Rules\Complexity\ForbiddenArrayMethodCallRule;
use Symplify\PHPStanRules\Rules\Domain\RequireAttributeNamespaceRule;
use Symplify\PHPStanRules\Rules\Domain\RequireExceptionNamespaceRule;
use Symplify\PHPStanRules\Rules\Enum\RequireUniqueEnumConstantRule;
use Symplify\PHPStanRules\Rules\ForbiddenExtendOfNonAbstractClassRule;
use Symplify\PHPStanRules\Rules\ForbiddenMultipleClassLikeInOneFileRule;
use Symplify\PHPStanRules\Rules\ForbiddenStaticClassConstFetchRule;
use Symplify\PHPStanRules\Rules\NoGlobalConstRule;
use Symplify\PHPStanRules\Rules\NoMissnamedDocTagRule;
use Symplify\PHPStanRules\Rules\PreventParentMethodVisibilityOverrideRule;

/*
 * The rules of symplify/phpstan-rules every PHIL and Tilttek package follows, chosen one by one: the whole sets would
 * forbid what the packages do on purpose, such as a setter that returns itself or a shared trait.
 * `phpstan/extension-installer` is told to ignore the package, which would load every set, so its
 * services are included from where it is installed.
 *
 * A project whose `vendor-bin/phpstan` does not have the package yet gets none of these rules.
 */
if (!class_exists(NoGlobalConstRule::class)) {
    return [];
}

$package = dirname((string) new ReflectionClass(NoGlobalConstRule::class)->getFileName(), 3);

return [
    'includes' => [$package.'/config/services/services.neon'],
    'rules' => [
        // A class is final, or abstract: extending a concrete class couples to its internals.
        ForbiddenExtendOfNonAbstractClassRule::class,
        NoGlobalConstRule::class,
        // An exception in an Exception namespace, an attribute in an Attribute namespace, an interface in a Contract
        // namespace when its namespace has one.
        RequireExceptionNamespaceRule::class,
        RequireAttributeNamespaceRule::class,
        CheckRequiredInterfaceInContractNamespaceRule::class,
        RequireUniqueEnumConstantRule::class,
        PreventParentMethodVisibilityOverrideRule::class,
        ForbiddenMultipleClassLikeInOneFileRule::class,
        ForbiddenStaticClassConstFetchRule::class,
        ForbiddenArrayMethodCallRule::class,
        NoMissnamedDocTagRule::class,
    ],
];
