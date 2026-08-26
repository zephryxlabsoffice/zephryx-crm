<?php

namespace App\Support;

/**
 * Identity tints, shared by every list that shows people or organisations.
 *
 * The tint is derived from the name rather than assigned, so the same person is
 * always the same colour wherever they appear, and no row falls through to a
 * default when a list grows past the size of the palette.
 */
class Avatar
{
    /** How many tints the palette holds; see components/ui.css. */
    public const TINTS = 7;

    public static function tint(string $name): string
    {
        $normalised = mb_strtolower(trim($name));

        if ($normalised === '') {
            return 'tint-1';
        }

        return 'tint-'.((crc32($normalised) % self::TINTS) + 1);
    }

    /**
     * One letter — for an organisation, where the name is a single thing.
     */
    public static function letter(string $name): string
    {
        return mb_strtoupper(mb_substr(trim($name), 0, 1)) ?: '?';
    }

    /**
     * Two letters — for a person, where first and last name distinguish people
     * a single initial would not.
     */
    public static function initials(string $name): string
    {
        return Shell::initials($name);
    }
}
