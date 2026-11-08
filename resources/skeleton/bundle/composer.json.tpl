{
    "name": "{{package}}",
    "description": "{{description}}",
    "license": "MIT",
    "type": "symfony-bundle",
    "keywords": [
        "symfony-bundle"
    ],
    "authors": [
        {
            "name": "Philippe Gamache",
            "email": "philippe@ph-il.ca",
            "homepage": "https://github.com/ph-il",
            "role": "Developer"
        }
    ],
    "homepage": "https://ph-il.ca",
    "require": {
        "php": ">={{php}}",
        "symfony/config": "^{{symfony}}",
        "symfony/dependency-injection": "^{{symfony}}"
    },
    "require-dev": {
        "phpunit/phpunit": "^13.4",
        "roave/security-advisories": "dev-latest"
    },
    "minimum-stability": "dev",
    "prefer-stable": true,
    "autoload": {
        "psr-4": {
            "{{namespace_json}}\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "{{namespace_json}}\\Tests\\": "tests/"
        }
    },
    "extra": {
        "branch-alias": {
            "dev-main": "0.1.x-dev"
        }
    }
}
