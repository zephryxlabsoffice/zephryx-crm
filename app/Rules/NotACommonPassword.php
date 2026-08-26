<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects passwords built from a well-known base word (foundation spec §4.7).
 *
 * The list is matched against a *normalised* form of the candidate — lower
 * cased, with common letter/digit substitutions undone and trailing digits and
 * punctuation stripped. That is what makes a short list useful: "Password123!",
 * "p@ssw0rd" and "PASSWORD" all reduce to "password" and are all caught by one
 * entry.
 */
class NotACommonPassword implements ValidationRule
{
    /**
     * Loaded once per process. The file is small and read from local disk, so
     * there is nothing to gain from caching it further.
     *
     * @var list<string>|null
     */
    protected static ?array $blocklist = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $blocklist = $this->blocklist();

        foreach ($this->candidates($value) as $candidate) {
            if ($candidate !== '' && in_array($candidate, $blocklist, true)) {
                $fail('That password is too easy to guess. Choose something less common.');

                return;
            }
        }
    }

    /**
     * The forms of a candidate worth comparing against the list.
     *
     * Both are needed: the normalised form catches "P@ssw0rd2024", while the
     * plain lower-cased form catches all-digit entries like "123456", which
     * normalisation would reduce to nothing.
     *
     * @return list<string>
     */
    protected function candidates(string $password): array
    {
        $lower = mb_strtolower(trim($password));

        return array_unique([$lower, $this->normalise($lower)]);
    }

    /**
     * Reduce a candidate to the base word an attacker would actually try.
     *
     * Order matters. Trailing padding is stripped first — otherwise the digits
     * in "password123" get substituted into letters and the word no longer
     * reduces to anything on the list.
     */
    protected function normalise(string $lowered): string
    {
        // "password123", "welcome!!!", "Zephryx2026" → drop the tail.
        $value = (string) preg_replace('/[\d\W_]+$/u', '', $lowered);

        // Then undo the substitutions people reach for when a site demands
        // "complexity": p@ssw0rd, l3tm31n.
        $value = strtr($value, [
            '@' => 'a', '4' => 'a',
            '3' => 'e',
            '1' => 'i', '!' => 'i', '|' => 'i',
            '0' => 'o',
            '$' => 's', '5' => 's',
            '7' => 't',
        ]);

        // A substitution can expose a new tail ("pass1!" → "passi"); tidy again.
        return (string) preg_replace('/[\d\W_]+$/u', '', $value);
    }

    /**
     * @return list<string>
     */
    protected function blocklist(): array
    {
        if (static::$blocklist !== null) {
            return static::$blocklist;
        }

        $path = resource_path('security/common-passwords.txt');

        if (! is_file($path)) {
            // Fail loudly in development rather than quietly accepting
            // "password" in production because a file was not deployed.
            report(new \RuntimeException("Common-password blocklist missing at [{$path}]."));

            return static::$blocklist = [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return static::$blocklist = array_values(array_filter(
            array_map(fn (string $line) => mb_strtolower(trim($line)), $lines),
            fn (string $line) => $line !== '' && ! str_starts_with($line, '#'),
        ));
    }

    /**
     * Clears the in-process cache. Only needed by tests that swap the file.
     */
    public static function flush(): void
    {
        static::$blocklist = null;
    }
}
