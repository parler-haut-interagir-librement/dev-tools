<?php

declare(strict_types=1);

namespace Phil\DevTools\Witness;

/**
 * Checks the witnesses of the PHPStan rules: files that must fail, each announcing in its docblock the identifier of
 * the rule that must stop it — "Expected identifier: `phil.unserialize`".
 *
 * A witness whose rule PHPStan no longer reports in that very file is a rule that went silent — renamed, removed,
 * written wrong —, and a witness that announces nothing is refused, so that a witness added is checked without anyone
 * thinking of it.
 *
 * A witness that announces `none` is the other half: what the rules beside it must accept. PHPStan must report
 * nothing there, so that a rule that grew too wide fails too.
 */
final class WitnessChecker
{
    private const string ANNOUNCEMENT = '/(?:Expected identifier|Identifiant attendu)\s*:\s*`([A-Za-z0-9._-]+)`/u';

    /**
     * The announcement of a witness that must pass.
     */
    public const string NONE = 'none';

    /**
     * @param list<string> $witnesses the witness files, absolute
     * @param string       $report    the output of PHPStan with `--error-format=json`
     *
     * @return list<string> the failures, empty when every witness is stopped by its rule
     */
    public function check(array $witnesses, string $report): array
    {
        $decoded = json_decode($report, true);

        if (!\is_array($decoded) || !\is_array($decoded['files'] ?? null)) {
            return ['The input is not the JSON output of PHPStan (--error-format=json).'];
        }

        $files = $decoded['files'];
        $failures = [];

        foreach ($witnesses as $witness) {
            $content = file_get_contents($witness);

            if (!\is_string($content) || 1 !== preg_match(self::ANNOUNCEMENT, $content, $announced)) {
                $failures[] = \sprintf('%s announces no identifier ("Expected identifier: `…`").', $witness);

                continue;
            }

            $failure = $this->failure($witness, $announced[1], $this->messages($files, $witness));

            if (null !== $failure) {
                $failures[] = $failure;
            }
        }

        return $failures;
    }

    /**
     * What is wrong with a witness, given the messages PHPStan reports in it, or null.
     *
     * @param list<mixed> $messages
     */
    private function failure(string $witness, string $announced, array $messages): ?string
    {
        $found = [];

        foreach ($messages as $message) {
            if (\is_array($message) && \is_string($message['identifier'] ?? null)) {
                $found[] = $message['identifier'];
            }
        }

        if (self::NONE === $announced) {
            return [] === $messages ? null : \sprintf('%s must pass, and PHPStan reports %s there; a rule is too wide.', $witness, [] === $found ? 'an error' : '`'.implode('`, `', array_unique($found)).'`');
        }

        return \in_array($announced, $found, true) ? null : \sprintf('%s: PHPStan does not report `%s` there; the rule is silent.', $witness, $announced);
    }

    /**
     * The messages PHPStan reports in a witness, found by its path as given or as resolved.
     *
     * @param array<mixed> $files
     *
     * @return list<mixed>
     */
    private function messages(array $files, string $witness): array
    {
        $real = realpath($witness);
        $errors = $files[$witness] ?? (false === $real ? null : ($files[$real] ?? null));

        return \is_array($errors) && \is_array($errors['messages'] ?? null) ? array_values($errors['messages']) : [];
    }
}
