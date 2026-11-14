<?php

declare(strict_types=1);

namespace Phil\DevTools\Tests\Unit\Split;

use Phil\DevTools\Process\ProcessResult;
use Phil\DevTools\Split\SecurityCommit;
use Phil\DevTools\Tests\Unit\Support\RecordingRunner;
use Phil\DevTools\Tests\Unit\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityCommit::class)]
final class SecurityCommitTest extends TestCase
{
    use TemporaryDirectory;

    private const string SPLIT = '0123456789abcdef0123456789abcdef01234567';

    private const string TIP = 'abababababababababababababababababababab';

    private const string BLOB = '1111111111111111111111111111111111111111';

    private const string TREE = '2222222222222222222222222222222222222222';

    private const string COMMIT = '3333333333333333333333333333333333333333';

    public function testTheCommitOnTopOfTheSplit(): void
    {
        $root = $this->temporaryDirectory();
        $work = $root.'/work';
        $this->put($work.'/stale', 'from an earlier run');
        $written = [];
        $runner = new RecordingRunner([new ProcessResult(0), new ProcessResult(0, self::BLOB."\n"), new ProcessResult(0), new ProcessResult(0, self::BLOB."\n"), new ProcessResult(0), new ProcessResult(0, self::TREE."\n"), self::identity(), new ProcessResult(0, self::COMMIT."\n")]);
        $runner->onRun = static function (array $command) use ($work, &$written): void {
            if ('read-tree' === $command[1]) {
                $written = scandir($work);
            }
        };

        $commit = new SecurityCommit($runner, $root)->build(self::SPLIT, ['.github/a.yml' => 'A', '.github/b.yml' => 'B'], '', $work);

        self::assertSame(self::COMMIT, $commit);
        self::assertSame(['.', '..'], $written);
        self::assertSame([
            'git read-tree '.self::SPLIT,
            'git hash-object -w '.$work.'/file-1',
            'git update-index --add --cacheinfo 100644,'.self::BLOB.',.github/a.yml',
            'git hash-object -w '.$work.'/file-2',
            'git update-index --add --cacheinfo 100644,'.self::BLOB.',.github/b.yml',
            'git write-tree',
            'git log -1 --format=%cn%n%ce%n%cd --date=raw '.self::SPLIT,
            'git commit-tree --no-gpg-sign '.self::TREE.' -p '.self::SPLIT.' -m Security files of the split'."\n\n".'Added on top of '.self::SPLIT.' by phil-dev split: .github/a.yml, .github/b.yml.',
        ], $runner->commands());
        $index = ['GIT_INDEX_FILE' => $work.'/index'];
        self::assertSame([$index, [], $index, [], $index, $index, []], array_column(\array_slice($runner->runs, 0, 7), 'environment'));
        self::assertSame([false, true, false, true, false, true, true, true], array_column($runner->runs, 'capture'));
        self::assertSame(array_fill(0, 8, $root), array_column($runner->runs, 'directory'));
        self::assertSame([
            'GIT_AUTHOR_NAME' => 'Split Person',
            'GIT_AUTHOR_EMAIL' => 'split@example.com',
            'GIT_AUTHOR_DATE' => '1700000000 +0100',
            'GIT_COMMITTER_NAME' => 'Split Person',
            'GIT_COMMITTER_EMAIL' => 'split@example.com',
            'GIT_COMMITTER_DATE' => '1700000000 +0100',
        ], $runner->runs[7]['environment']);
        self::assertDirectoryDoesNotExist($work);
    }

    public function testTheFilesAreWrittenForGit(): void
    {
        $work = $this->temporaryDirectory().'/work';
        $contents = [];
        $runner = new RecordingRunner([new ProcessResult(0), new ProcessResult(0, self::BLOB), new ProcessResult(0), new ProcessResult(0, self::BLOB), new ProcessResult(0), new ProcessResult(0, self::TREE), self::identity(), new ProcessResult(0, self::COMMIT)]);
        $runner->onRun = static function (array $command) use ($work, &$contents): void {
            if ('update-index' === $command[1]) {
                $contents[] = file_get_contents($work.'/file-'.(\count($contents) + 1));
            }
        };

        new SecurityCommit($runner, $this->temporaryDirectory())->build(self::SPLIT, ['a' => "A\n", 'b' => 'B'], '', $work);

        self::assertSame(["A\n", 'B'], $contents);
    }

    public function testAPublishedTipThatTheSplitDoesNotDescendFromIsTheSecondParent(): void
    {
        $runner = new RecordingRunner([...self::tree(), self::identity(), new ProcessResult(0, self::TREE.'x'), new ProcessResult(1), new ProcessResult(0, self::COMMIT)]);

        $commit = new SecurityCommit($runner, $this->temporaryDirectory())->build(self::SPLIT, ['f' => 'F'], self::TIP, $this->temporaryDirectory().'/work');

        self::assertSame(self::COMMIT, $commit);
        $commands = $runner->commands();
        self::assertSame('git rev-parse '.self::TIP.'^{tree}', $commands[5]);
        self::assertSame('git merge-base --is-ancestor '.self::TIP.' '.self::SPLIT, $commands[6]);
        self::assertFalse($runner->runs[6]['capture']);
        self::assertStringStartsWith('git commit-tree --no-gpg-sign '.self::TREE.' -p '.self::SPLIT.' -p '.self::TIP.' -m ', $commands[7]);
    }

    public function testATipTheSplitDescendsFromIsNoParent(): void
    {
        $runner = new RecordingRunner([...self::tree(), self::identity(), new ProcessResult(0, 'cccccccccccccccccccccccccccccccccccccccc'), new ProcessResult(0), new ProcessResult(0, self::COMMIT)]);

        new SecurityCommit($runner, $this->temporaryDirectory())->build(self::SPLIT, ['f' => 'F'], self::TIP, $this->temporaryDirectory().'/work');

        self::assertStringStartsWith('git commit-tree --no-gpg-sign '.self::TREE.' -p '.self::SPLIT.' -m ', $runner->commands()[7]);
    }

    public function testATipWithTheSameTreeIsKept(): void
    {
        $runner = new RecordingRunner([...self::tree(), self::identity(), new ProcessResult(0, self::TREE."\n")]);

        self::assertSame(self::TIP, new SecurityCommit($runner, $this->temporaryDirectory())->build(self::SPLIT, ['f' => 'F'], self::TIP, $this->temporaryDirectory().'/work'));
        self::assertCount(6, $runner->runs);
    }

    /**
     * @return iterable<string, array{list<ProcessResult>, int}>
     */
    public static function failures(): iterable
    {
        $tree = self::tree();

        yield 'the index' => [[new ProcessResult(128)], 1];
        yield 'a blob that fails' => [[$tree[0], new ProcessResult(1, self::BLOB)], 2];
        yield 'a blob that is not one' => [[$tree[0], new ProcessResult(0, 'blob')], 2];
        yield 'the index entry' => [[$tree[0], $tree[1], new ProcessResult(1)], 3];
        yield 'the tree' => [[...\array_slice($tree, 0, 3), new ProcessResult(1, self::TREE)], 4];
        yield 'the identity that fails' => [[...$tree, new ProcessResult(1, "a\nb\nc\n")], 5];
        yield 'an identity of two lines' => [[...$tree, new ProcessResult(0, "a\nb\n")], 5];
        yield 'an identity of four lines' => [[...$tree, new ProcessResult(0, "a\nb\nc\nd\n")], 5];
        yield 'the commit' => [[...$tree, self::identity(), new ProcessResult(1, self::COMMIT)], 6];
        yield 'a commit and more' => [[...$tree, self::identity(), new ProcessResult(0, self::COMMIT.' x')], 6];
    }

    /**
     * @param list<ProcessResult> $results
     */
    #[DataProvider('failures')]
    public function testAFailedStepBuildsNothing(array $results, int $runs): void
    {
        $runner = new RecordingRunner($results);
        $work = $this->temporaryDirectory().'/work';

        self::assertNull(new SecurityCommit($runner, $this->temporaryDirectory())->build(self::SPLIT, ['f' => 'F'], '', $work));
        self::assertCount($runs, $runner->runs);
        self::assertDirectoryDoesNotExist($work);
    }

    public function testAnUnexpectedAnswerOfMergeBaseBuildsNothing(): void
    {
        $runner = new RecordingRunner([...self::tree(), self::identity(), new ProcessResult(0, 'other'), new ProcessResult(128)]);

        self::assertNull(new SecurityCommit($runner, $this->temporaryDirectory())->build(self::SPLIT, ['f' => 'F'], self::TIP, $this->temporaryDirectory().'/work'));
        self::assertCount(7, $runner->runs);
    }

    public function testASha256Repository(): void
    {
        $object = str_repeat('a', 64);
        $runner = new RecordingRunner([new ProcessResult(0), new ProcessResult(0, $object), new ProcessResult(0), new ProcessResult(0, $object), self::identity(), new ProcessResult(0, $object)]);

        self::assertSame($object, new SecurityCommit($runner, $this->temporaryDirectory())->build($object, ['f' => 'F'], '', $this->temporaryDirectory().'/work'));
    }

    /**
     * @return iterable<string, array{ProcessResult, string|null, list<string>}>
     */
    public static function tips(): iterable
    {
        yield 'no branch' => [new ProcessResult(0, "\n"), '', ['git ls-remote --heads git@github.com:o/n.git refs/heads/main']];
        yield 'a branch' => [new ProcessResult(0, self::TIP."\trefs/heads/main\n"), self::TIP, ['git ls-remote --heads git@github.com:o/n.git refs/heads/main', 'git fetch --no-tags git@github.com:o/n.git refs/heads/main']];
        yield 'an unreadable remote' => [new ProcessResult(128), null, ['git ls-remote --heads git@github.com:o/n.git refs/heads/main']];
        yield 'another branch only' => [new ProcessResult(0, self::TIP."\trefs/heads/main-old\n"), null, ['git ls-remote --heads git@github.com:o/n.git refs/heads/main']];
        yield 'something else' => [new ProcessResult(0, "x\trefs/heads/main\n"), null, ['git ls-remote --heads git@github.com:o/n.git refs/heads/main']];
        yield 'a longer object' => [new ProcessResult(0, self::TIP."ab\trefs/heads/main\n"), null, ['git ls-remote --heads git@github.com:o/n.git refs/heads/main']];
    }

    /**
     * @param list<string> $commands
     */
    #[DataProvider('tips')]
    public function testTheTipOfTheRemote(ProcessResult $listed, ?string $expected, array $commands): void
    {
        $root = $this->temporaryDirectory();
        $runner = new RecordingRunner([$listed]);

        self::assertSame($expected, new SecurityCommit($runner, $root)->remoteTip('git@github.com:o/n.git', 'main'));
        self::assertSame($commands, $runner->commands());
        self::assertSame(array_fill(0, \count($commands), $root), array_column($runner->runs, 'directory'));
        self::assertSame(\array_slice([true, false], 0, \count($commands)), array_column($runner->runs, 'capture'));
    }

    public function testABranchNameIsMatchedAsItIsWritten(): void
    {
        $runner = new RecordingRunner([new ProcessResult(0, self::TIP."\trefs/heads/a-b\n")]);

        self::assertNull(new SecurityCommit($runner, $this->temporaryDirectory())->remoteTip('git@github.com:o/n.git', 'a.b'));
    }

    public function testAFailedFetch(): void
    {
        $runner = new RecordingRunner([new ProcessResult(0, "x\trefs/heads/feature\n".self::TIP."\trefs/heads/feature/main\n".self::TIP."\trefs/heads/main\n"), new ProcessResult(1)]);

        self::assertNull(new SecurityCommit($runner, $this->temporaryDirectory())->remoteTip('r', 'main'));
        self::assertCount(2, $runner->runs);
    }

    /**
     * What git answers from the index to the tree, for one file.
     *
     * @return list<ProcessResult>
     */
    private static function tree(): array
    {
        return [new ProcessResult(0), new ProcessResult(0, self::BLOB."\n"), new ProcessResult(0), new ProcessResult(0, self::TREE."\n")];
    }

    private static function identity(): ProcessResult
    {
        return new ProcessResult(0, "Split Person\nsplit@example.com\n1700000000 +0100\n");
    }
}
