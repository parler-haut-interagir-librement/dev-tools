<?php

declare(strict_types=1);

namespace Phil\DevTools\Split;

use Phil\DevTools\Files;
use Phil\DevTools\Process\ProcessRunner;

/**
 * The commit that adds the security files on top of a split commit, built with git's plumbing in the repository the
 * split was made in: no checkout, no branch, and nothing of the history rewritten.
 *
 * Its tree is the split's, with the files added or replaced; its author and committer are the split commit's committer,
 * at the same date, and it is not signed, so that the same split and the same files always give the same commit. When
 * the branch of the split repository already has a tip, that tip is the second parent of the commit unless the split
 * already descends from it: the push stays a fast-forward from one split to the next, and the history published
 * before is kept. A tip whose tree is the one built already holds these files, and is used as it is.
 */
final readonly class SecurityCommit
{
    private const string OBJECT_PATTERN = '/^[0-9a-f]{40}(?:[0-9a-f]{24})?$/';

    public function __construct(private ProcessRunner $runner, private string $root)
    {
    }

    /**
     * The commit the branch of the remote repository points at, fetched: '' when the branch does not exist yet, null
     * when the repository cannot be read.
     */
    public function remoteTip(string $repository, string $branch): ?string
    {
        $ref = 'refs/heads/'.$branch;
        $listed = $this->runner->capture(['git', 'ls-remote', '--heads', $repository, $ref], $this->root);

        if (!$listed->successful()) {
            return null;
        }

        if ('' === trim($listed->output)) {
            return '';
        }

        if (1 !== preg_match('#^([0-9a-f]{40}(?:[0-9a-f]{24})?)\t'.preg_quote($ref, '#').'$#m', $listed->output, $tip)) {
            return null;
        }

        return $this->runner->run(['git', 'fetch', '--no-tags', $repository, $ref], $this->root)->successful() ? $tip[1] : null;
    }

    /**
     * The commit of the split with the files, by path; null when a step of git fails.
     *
     * @param array<string, string> $files
     * @param string                $tip   the tip of the target branch, fetched, or '' for none
     * @param string                $work  a directory of its own, removed afterwards
     */
    public function build(string $split, array $files, string $tip, string $work): ?string
    {
        if (is_dir($work)) {
            Files::removeDirectory($work);
        }

        mkdir($work, recursive: true);

        try {
            $tree = $this->tree($split, $files, $work);

            if (null === $tree) {
                return null;
            }

            return $this->commit($split, $tree, $tip, array_keys($files));
        } finally {
            Files::removeDirectory($work);
        }
    }

    /**
     * The tree of the split with the files, written through an index of its own.
     *
     * @param array<string, string> $files
     */
    private function tree(string $split, array $files, string $work): ?string
    {
        $index = ['GIT_INDEX_FILE' => $work.'/index'];

        if (!$this->runner->run(['git', 'read-tree', $split], $this->root, $index)->successful()) {
            return null;
        }

        $number = 0;

        foreach ($files as $path => $content) {
            $file = $work.'/file-'.++$number;
            file_put_contents($file, $content);
            $blob = $this->object(['git', 'hash-object', '-w', $file]);

            if (null === $blob || !$this->runner->run(['git', 'update-index', '--add', '--cacheinfo', '100644,'.$blob.','.$path], $this->root, $index)->successful()) {
                return null;
            }
        }

        return $this->object(['git', 'write-tree'], $index);
    }

    /**
     * @param list<string> $paths
     */
    private function commit(string $split, string $tree, string $tip, array $paths): ?string
    {
        $identity = $this->runner->capture(['git', 'log', '-1', '--format=%cn%n%ce%n%cd', '--date=raw', $split], $this->root);
        $lines = explode("\n", rtrim($identity->output, "\n"));

        if (!$identity->successful() || 3 !== \count($lines)) {
            return null;
        }

        $parents = ['-p', $split];

        if ('' !== $tip) {
            if ($tree === $this->object(['git', 'rev-parse', $tip.'^{tree}'])) {
                return $tip;
            }

            $descends = $this->runner->run(['git', 'merge-base', '--is-ancestor', $tip, $split], $this->root)->exitCode;

            if (1 === $descends) {
                $parents = [...$parents, '-p', $tip];
            } elseif (0 !== $descends) {
                return null;
            }
        }

        [$name, $email, $date] = $lines;
        $message = "Security files of the split\n\nAdded on top of ".$split.' by phil-dev split: '.implode(', ', $paths).'.';

        return $this->object(['git', 'commit-tree', '--no-gpg-sign', $tree, ...$parents, '-m', $message], [
            'GIT_AUTHOR_NAME' => $name,
            'GIT_AUTHOR_EMAIL' => $email,
            'GIT_AUTHOR_DATE' => $date,
            'GIT_COMMITTER_NAME' => $name,
            'GIT_COMMITTER_EMAIL' => $email,
            'GIT_COMMITTER_DATE' => $date,
        ]);
    }

    /**
     * The object a git command prints, or null when it fails or prints something else.
     *
     * @param non-empty-list<string> $command
     * @param array<string, string>  $environment
     */
    private function object(array $command, array $environment = []): ?string
    {
        $result = $this->runner->capture($command, $this->root, $environment);
        $object = trim($result->output);

        return $result->successful() && 1 === preg_match(self::OBJECT_PATTERN, $object) ? $object : null;
    }
}
