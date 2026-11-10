<?php

declare(strict_types=1);

use Kocal\PHPStanSymfonyUX\Rules\LiveComponent\AsLiveComponentArgumentsMustBeValidRule;
use Kocal\PHPStanSymfonyUX\Rules\LiveComponent\LiveActionMethodsVisibilityRule;
use Kocal\PHPStanSymfonyUX\Rules\LiveComponent\LiveListenerMethodsVisibilityRule;
use Kocal\PHPStanSymfonyUX\Rules\LiveComponent\LivePropHydrationMethodsRule;
use Kocal\PHPStanSymfonyUX\Rules\LiveComponent\LivePropModifierMethodRule;
use Kocal\PHPStanSymfonyUX\Rules\LiveComponent\LiveResponseMustBeReturnedByLiveActionRule;
use Kocal\PHPStanSymfonyUX\Rules\TwigComponent\ClassMustBeFinalRule;
use Kocal\PHPStanSymfonyUX\Rules\TwigComponent\ExposePublicPropsMustBeFalseRule;
use Kocal\PHPStanSymfonyUX\Rules\TwigComponent\ForbiddenAttributesPropertyRule;
use Kocal\PHPStanSymfonyUX\Rules\TwigComponent\ForbiddenClassPropertyRule;
use Kocal\PHPStanSymfonyUX\Rules\TwigComponent\ForbiddenReadonlyRule;
use Kocal\PHPStanSymfonyUX\Rules\TwigComponent\FromMethodMethodMustExistRule;
use Kocal\PHPStanSymfonyUX\Rules\TwigComponent\MethodsVisibilityRule;
use Kocal\PHPStanSymfonyUX\Rules\TwigComponent\PostMountMethodSignatureRule;
use Kocal\PHPStanSymfonyUX\Rules\TwigComponent\PreMountMethodSignatureRule;
use Kocal\PHPStanSymfonyUX\Rules\TwigComponent\PublicPropertiesMustBeCamelCaseRule;
use Phil\DevTools\PhpStan\Rules\ClassNameMustEndWithComponentRule;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/*
 * The rules on Live Components and Twig Components: the rule of this package on the suffix of their names, then those of
 * kocal/phpstan-symfony-ux, one by one: a new rule of that package is a decision, not a side effect of an update. Each
 * one only looks at a class carrying `#[AsTwigComponent]` or `#[AsLiveComponent]`, and matches the attribute by its name:
 * a project without Symfony UX is not affected.
 *
 * `ClassNameMustNotEndWithComponentRule` of that package asks the opposite of the suffix rule, and stays out. A project
 * whose `vendor-bin/phpstan` does not have the package yet gets the suffix rule alone. `phpstan.neon` keeps
 * `forbiddenReadonlyClass` out, which contradicts Rector.
 */
if (!class_exists(ClassMustBeFinalRule::class)) {
    return ['rules' => [ClassNameMustEndWithComponentRule::class]];
}

$rules = [
    ClassNameMustEndWithComponentRule::class,
    AsLiveComponentArgumentsMustBeValidRule::class,
    LiveActionMethodsVisibilityRule::class,
    LiveListenerMethodsVisibilityRule::class,
    LivePropHydrationMethodsRule::class,
    LivePropModifierMethodRule::class,
    LiveResponseMustBeReturnedByLiveActionRule::class,
    ClassMustBeFinalRule::class,
    ExposePublicPropsMustBeFalseRule::class,
    ForbiddenClassPropertyRule::class,
    ForbiddenReadonlyRule::class,
    FromMethodMethodMustExistRule::class,
    MethodsVisibilityRule::class,
    PostMountMethodSignatureRule::class,
    PreMountMethodSignatureRule::class,
    PublicPropertiesMustBeCamelCaseRule::class,
];

/*
 * This rule reads the default of the attribute's argument, and stops PHPStan with an internal error when a class carries
 * `#[AsTwigComponent]` without Symfony UX installed, as a package with an optional component may. PHPStan loads the
 * autoloader of the directory it runs from before it reads this file.
 */
if (class_exists(AsTwigComponent::class)) {
    $rules[] = ForbiddenAttributesPropertyRule::class;
}

return ['rules' => $rules];
