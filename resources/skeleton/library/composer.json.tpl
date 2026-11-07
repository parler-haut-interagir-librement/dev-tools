{
    "name": "{{package}}",
    "description": "{{description}}",
    "type": "library",
    "keywords": [],
    "license": "MIT",
    "homepage": "https://ph-il.ca",
    "authors": [
        {
            "name": "Philippe Gamache",
            "email": "philippe@ph-il.ca",
            "homepage": "https://github.com/ph-il",
            "role": "Developer"
        }
    ],
    "require": {
        "php": ">={{php}}"
    },
    "require-dev": {
        "phpunit/phpunit": "^13.4"
    },
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
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
