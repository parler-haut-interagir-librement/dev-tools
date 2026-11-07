<?php

declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\HaveNameMatching;
use Arkitect\Expression\ForClasses\IsFinal;
use Arkitect\Expression\ForClasses\IsInterface;
use Arkitect\Expression\ForClasses\IsNotReadonly;
use Arkitect\Expression\ForClasses\IsReadonly;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\NotImplement;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\DSL\ArchRule;
use Arkitect\Rules\Rule;
use Phil\DevTools\Config\Tooling;

require_once __DIR__.'/bootstrap.php';

/*
 * The shared shape rules. Arkitect checks what a class *is*; Deptrac checks where its dependencies point.
 *
 * A project's `arkitect.php` calls the closure with its own directory. The classes come from the production code of
 * the root and of each package, found through their `composer.json`; the rules are written once for every namespace
 * those declare, and each manifest's `arkitect.rules` adds its own.
 *
 * @return Closure(string): Closure(Config): void
 */
return static function (string $root): Closure {
    $tooling = Tooling::fromEnvironment($root);

    return static function (Config $config) use ($tooling): void {
        $directories = $tooling->sourceDirectories();

        if ([] === $directories) {
            return;
        }

        $namespaces = $tooling->namespaces();

        /**
         * Every namespace's `<namespace>\<layer>` and `<namespace>\*\<layer>`: a package's own layer, and the layer of
         * each context of an application.
         *
         * @return list<string>
         */
        $in = static function (string $layer) use ($namespaces): array {
            $patterns = [];

            foreach ($namespaces as $namespace) {
                $patterns[] = $namespace.'\\'.$layer;
                $patterns[] = $namespace.'\\*\\'.$layer;
            }

            return $patterns;
        };

        /** @var list<ArchRule> $rules */
        $rules = [
            // The prerequisite of every other rule: a class outside the declared namespaces passes through every
            // boundary rule without breaking any.
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces('*'))
                ->should(new ResideInOneOfTheseNamespaces(...array_map(
                    static fn (string $namespace): string => $namespace.'\\*',
                    [...$namespaces, ...$tooling->arkitectExtraNamespaces()],
                )))
                ->because('a class outside the namespaces of the packages is a class no boundary rule covers'),

            // The shape of the domain.
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces(...$in('Domain\\Model')))
                ->should(new IsFinal())
                ->because('a domain model that is extended has an invariant a subclass can get around'),
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces(...$in('Domain\\Model')))
                ->should(new IsNotReadonly())
                ->because('a Doctrine entity changes state in place under the unit of work'),
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces(...$in('Domain\\ValueObject')))
                ->should(new IsFinal())
                ->because('a value object is its value; subclassing it breaks equality'),
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces(...$in('Domain\\ValueObject')))
                ->should(new IsReadonly())
                ->because('a value object that can change after its construction is not one'),

            // The repositories.
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces(...$in('Domain\\Repository')))
                ->should(new IsInterface())
                ->because('the domain declares the persistence contract; the infrastructure implements it'),
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces(...$in('Domain\\Repository')))
                ->should(new HaveNameMatching('*Repository'))
                ->because('a name that does not say what the thing is, is a name the review has to guess'),

            // What the domain may depend on.
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces(...$in('Domain')))
                ->should(new NotDependsOnTheseNamespaces(['Doctrine'], ['Doctrine\\ORM\\Mapping']))
                ->because('nothing of Doctrine enters a domain, except the mapping attributes'),
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces(...$in('Domain')))
                ->should(new NotDependsOnTheseNamespaces(['Symfony\\Component\\Validator']))
                ->because('a validator produces violations for a user, a domain produces a failed invariant'),
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces(...$in('Domain')))
                ->should(new NotDependsOnTheseNamespaces([
                    'Symfony\\Component\\DependencyInjection',
                    'Symfony\\Component\\HttpFoundation',
                    'Symfony\\Component\\HttpKernel',
                    'Symfony\\Component\\Messenger',
                    'Symfony\\Component\\Serializer',
                    'Symfony\\Component\\Form',
                    'Symfony\\Component\\Security',
                    'Symfony\\Bundle',
                ]))
                ->because('a domain has no container, no configuration, no I/O and no autowiring'),

            // The application talks to the domain through interfaces; Doctrine lives in the infrastructure.
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces(...$in('Application')))
                ->should(new NotDependsOnTheseNamespaces(['Doctrine\\ORM', 'Doctrine\\DBAL']))
                ->because('the application talks to the domain through interfaces; Doctrine lives in the infrastructure'),

            // A domain event is a fact of the domain, not a message.
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespaces(...$in('Domain\\Event')))
                ->should(new NotImplement('Symfony\\Component\\Messenger\\Envelope'))
                ->because('a domain event is a fact of the domain, not a transport envelope'),
        ];

        foreach ($tooling->arkitectRules() as $extra) {
            foreach ($extra() as $rule) {
                if ($rule instanceof ArchRule) {
                    $rules[] = $rule;
                }
            }
        }

        $config->add(ClassSet::fromDir(...$directories), ...$rules);
    };
};
