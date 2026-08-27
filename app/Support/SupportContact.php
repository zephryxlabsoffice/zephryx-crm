<?php

namespace App\Support;

/**
 * The one place that builds a "contact support" link.
 *
 * Four surfaces need it — the landing page, the login footer, the 403 and the
 * generic error pages — and each was assembling its own `mailto:` with its own
 * subject encoding. Changing the address should be one edit, not four.
 *
 * A mailto rather than a form: the landing page is public, and a public
 * unauthenticated write endpoint is a spam and abuse surface this company does
 * not need (foundation spec §13.3).
 */
class SupportContact
{
    public static function address(): string
    {
        return (string) config('zephryx.support.email');
    }

    /**
     * A mailto link, with the subject pre-filled so replies are easy to triage.
     */
    public static function mailto(?string $subject = null): string
    {
        $subject ??= config('zephryx.brand.name').' '.config('zephryx.brand.suffix').' — support request';

        return 'mailto:'.self::address().'?subject='.rawurlencode($subject);
    }
}
