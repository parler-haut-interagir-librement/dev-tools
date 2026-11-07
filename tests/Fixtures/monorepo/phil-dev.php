<?php

declare(strict_types=1);

return [
    'paths' => ['src', 'missing'],
    'packages' => ['packages/*'],
    'cs' => ['rules' => ['phpdoc_to_comment' => true]],
    'deptrac' => [
        'configs' => ['deptrac-contexts.yaml'],
        'layers' => ['ExampleApp' => '^Example\\\\.*'],
    ],
    'infection' => ['testFrameworkOptions' => '--testsuite=Fast'],
    'docs' => ['forbidden' => ['/\bForbiddenName\b/']],
];
