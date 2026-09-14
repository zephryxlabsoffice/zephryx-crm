<?php

namespace App\Support;

/**
 * The document a person is identified by.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHY THIS IS NOT AN AADHAAR COLUMN (decided 2026-09-12)
 *
 * The record held Aadhaar alone, and a private employer cannot require it —
 * Puttaswamy, 2018. Asked to make it mandatory, the honest answer was that the
 * requirement is an IDENTITY DOCUMENT CARRYING AN ADDRESS, and Aadhaar is one
 * of four answers to that rather than the question itself.
 *
 * These four and no more, because each one states an address on its face. A
 * PAN card does not, which is why PAN stays a separate field for a separate
 * purpose (TDS) rather than joining this list.
 *
 * THE VOCABULARY IS HERE AND THE MASKING IS IN Sensitive
 *
 * Two questions that look like one: what a document is called, and how much of
 * its number may appear on a screen. Kept apart because the second is a rule
 * with a regulator behind it for one of the four, and a class that answered
 * both would invite a fifth type to be added with a label and no masking rule.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class IdProof
{
    public const AADHAAR = 'aadhaar';

    public const VOTER_ID = 'voter_id';

    public const PASSPORT = 'passport';

    public const DRIVING_LICENCE = 'driving_licence';

    /** What a record with no document on file reads as. */
    public const UNRECORDED = 'No ID proof on file';

    /**
     * The four, in the order the dropdown offers them.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        self::AADHAAR => 'Aadhaar',
        self::VOTER_ID => 'Voter ID',
        self::PASSPORT => 'Passport',
        self::DRIVING_LICENCE => 'Driving Licence',
    ];

    /**
     * What the document is called, for a label beside its masked number.
     *
     * An unknown or missing type says so rather than falling back to the
     * commonest document: "Aadhaar" printed over somebody's passport number is
     * a label that is wrong without looking wrong.
     */
    public static function label(?string $type): string
    {
        return self::TYPES[$type] ?? self::UNRECORDED;
    }

    public static function isType(?string $type): bool
    {
        return $type !== null && array_key_exists($type, self::TYPES);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::TYPES;
    }
}
