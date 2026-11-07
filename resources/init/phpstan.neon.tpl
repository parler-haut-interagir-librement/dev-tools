# The shared PHPStan configuration of phil/dev-tools. The paths and exceptions come from phil-dev.php; what only this
# project needs (a Symfony container, a Doctrine object manager) goes below.
includes:
    - {{config}}/phpstan.neon

parameters:
    tmpDir: var/phpstan
