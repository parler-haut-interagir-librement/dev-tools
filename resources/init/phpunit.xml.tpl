<?xml version="1.0" encoding="UTF-8"?>

<!--
    In a monorepo, the suites of every package are found by their glob: adding a package adds no line here.
    Unit runs without a kernel and touches nothing: it is the suite Infection replays once per mutant.
    Integration boots a kernel or talks to a real server; a test that needs a server reads its URL from the
    environment and is skipped without it (phil-dev services).
-->
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         colors="true"
         failOnDeprecation="true"
         failOnNotice="true"
         failOnWarning="true"
         bootstrap="vendor/autoload.php"
         cacheDirectory="var/phpunit"
>
    <php>
        <ini name="display_errors" value="1" />
        <ini name="error_reporting" value="-1" />
        <server name="SHELL_VERBOSITY" value="-1" />
    </php>

    <testsuites>
        <testsuite name="Unit">
{{unit}}
        </testsuite>
        <testsuite name="Integration">
{{integration}}
        </testsuite>
    </testsuites>

    <source ignoreSuppressionOfDeprecations="true"
            ignoreIndirectDeprecations="true"
            restrictNotices="true"
            restrictWarnings="true"
    >
        <include>
{{source}}
        </include>
    </source>
{{extensions}}</phpunit>
