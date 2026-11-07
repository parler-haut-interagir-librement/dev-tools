<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Witness;

use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use Phil\DevTools\Witness\WitnessChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WitnessChecker::class)]
final class WitnessCheckerTest extends TestCase
{
    use TemporaryDirectory;

    public function testEachWitnessIsStoppedByItsRule(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/Stopped.php', "<?php\n/** Expected identifier: `phil.unserialize` */");
        $this->put($dir.'/French.php', "<?php\n/** Identifiant attendu : `phil.dump` */");
        $this->put($dir.'/Silent.php', "<?php\n/** Expected identifier: `phil.weakRandom` */");
        $this->put($dir.'/Mute.php', "<?php\n/** Nothing announced. */");
        $this->put($dir.'/Empty.php', "<?php\n/** Expected identifier: `phil.dump` */");

        $report = json_encode(['files' => [
            $dir.'/Stopped.php' => ['messages' => [['identifier' => 'other'], ['identifier' => 'phil.unserialize']]],
            $dir.'/French.php' => ['messages' => [['message' => 'no identifier'], ['identifier' => 'phil.dump'], 'odd']],
            $dir.'/Mute.php' => ['nothing' => true],
            $dir.'/Empty.php' => ['nothing' => true],
            $dir.'/Silent.php' => ['messages' => [['identifier' => 'phil.dump']]],
        ]], \JSON_THROW_ON_ERROR);

        self::assertSame([
            $dir.'/Mute.php announces no identifier ("Expected identifier: `…`").',
            $dir.'/Silent.php: PHPStan does not report `phil.weakRandom` there; the rule is silent.',
            $dir.'/Empty.php: PHPStan does not report `phil.dump` there; the rule is silent.',
        ], new WitnessChecker()->check([$dir.'/Stopped.php', $dir.'/French.php', $dir.'/Mute.php', $dir.'/Silent.php', $dir.'/Empty.php'], $report));
    }

    public function testTheReportIsReadByTheRealPathToo(): void
    {
        $dir = $this->temporaryDirectory();
        $this->put($dir.'/A.php', "<?php\n/** Expected identifier: `x.y` */");

        $report = json_encode(['files' => [$dir.'/A.php' => ['messages' => [['identifier' => 'x.y']]]]], \JSON_THROW_ON_ERROR);

        self::assertSame([], new WitnessChecker()->check([$dir.'/./A.php'], $report));

        $both = json_encode(['files' => [$dir.'/./A.php' => ['messages' => [['identifier' => 'x.y']]], $dir.'/A.php' => ['messages' => []]]], \JSON_THROW_ON_ERROR);

        self::assertSame([], new WitnessChecker()->check([$dir.'/./A.php'], $both));
    }

    public function testTheInputMustBeTheJsonOfPhpStan(): void
    {
        self::assertSame(['The input is not the JSON output of PHPStan (--error-format=json).'], new WitnessChecker()->check([], 'nope'));
        self::assertSame(['The input is not the JSON output of PHPStan (--error-format=json).'], new WitnessChecker()->check([], '{"files": 3}'));
    }
}
