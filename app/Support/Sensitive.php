<?php

namespace App\Support;

/**
 * Masking for identifiers that must not be rendered in full.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE RULE THIS CLASS EXISTS TO ENFORCE
 *
 * Masking is done HERE, in PHP, before the value reaches the view. The full
 * number must never be written into the HTML and hidden with CSS, never sent in
 * a data attribute, never included in a JSON payload the page filters
 * client-side. Anything the browser receives has already been read by whoever
 * is sitting at the browser — "hidden" markup is not a control, it is a
 * decoration over a leak.
 *
 * A controller therefore hands the view the MASKED string. There is deliberately
 * no accessor here that a Blade template could call to get the full value.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT THE BACKEND STILL OWES (foundation spec §6, §11.1)
 *
 * 1. ENCRYPTED AT REST. PAN, Aadhaar and bank account numbers are stored with
 *    Laravel's `encrypted` cast, not as plaintext columns. A database backup
 *    downloaded from cPanel is a file somebody can email.
 *
 * 2. REVEALING IS A REQUEST, NOT A RENDER. If the full number is ever needed —
 *    verifying a bank transfer, filing a return — it comes from a dedicated
 *    route that checks the permission, writes an audit entry naming who looked
 *    at whose record and when, and returns the one value asked for. It is not
 *    something a page renders because the viewer happens to be an admin.
 *
 * 3. NEVER LOGGED. These fields go in the framework's `dontFlash`/redaction
 *    list so a validation failure or an exception report cannot spill them into
 *    a log file that is easier to read than the database.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ON AADHAAR SPECIFICALLY
 *
 * Kept at the owner's explicit decision (2026-08-27), against the
 * recommendation to drop it: payroll runs on PAN and bank details, and holding
 * Aadhaar brings the Aadhaar Act 2016 and the UIDAI regulations with it —
 * consent and purpose limitation, encryption, breach reporting, and penalties
 * that apply to private companies.
 *
 * Since it is held, it is held properly. UIDAI's own rule is that a masked
 * Aadhaar shows only the last four digits, which is what `aadhaar()` produces,
 * and it is never displayed to anyone but the person themselves and the owner.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class Sensitive
{
    /** What a masked value reads as when there is nothing recorded. */
    public const ABSENT = 'Not recorded';

    /**
     * Aadhaar, masked to the last four digits: `XXXX XXXX 1234`.
     *
     * The grouping is kept because that is how the number is printed on the
     * card, so a person can recognise their own at a glance without the rest
     * of it being on screen.
     */
    public static function aadhaar(?string $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if ($digits === null || strlen($digits) < 4) {
            return self::ABSENT;
        }

        return 'XXXX XXXX '.substr($digits, -4);
    }

    /**
     * PAN, masked to the last four characters: `XXXXXX234F`.
     *
     * A PAN is ten characters — five letters, four digits, a check letter.
     * Enough is shown to confirm which record is on screen, and not enough to
     * quote the number anywhere it matters.
     */
    public static function pan(?string $value): string
    {
        $pan = strtoupper(trim((string) $value));

        if (strlen($pan) < 4) {
            return self::ABSENT;
        }

        return str_repeat('X', max(0, strlen($pan) - 4)).substr($pan, -4);
    }

    /**
     * A bank account number, masked to the last four digits.
     *
     * The bullet count is fixed rather than matching the real length: Indian
     * account numbers run from nine to eighteen digits, and rendering the true
     * length narrows a guess for no benefit to the reader.
     */
    public static function accountNumber(?string $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if ($digits === null || strlen($digits) < 4) {
            return self::ABSENT;
        }

        return '•••• •••• '.substr($digits, -4);
    }

    /**
     * An IFSC code, in full and unmasked — deliberately.
     *
     * It identifies a bank branch, not a person. It is published by the RBI and
     * printed on every cheque. Masking it would suggest the other fields on the
     * card are protected by obscurity too, which is the wrong lesson.
     */
    public static function ifsc(?string $value): string
    {
        $ifsc = strtoupper(trim((string) $value));

        return $ifsc === '' ? self::ABSENT : $ifsc;
    }

    /**
     * Whether a viewer may see another person's sensitive identifiers at all.
     *
     * Front end only for now, and it returns the safe answer rather than a
     * permissive one: until the RBAC engine exists (§5), nothing but a person's
     * own record is treated as visible. The backend replaces this with a real
     * permission check plus an audit entry.
     *
     * Note what this is NOT: it is not the thing that stops a leak. The query
     * must not load the field in the first place unless the viewer may have it
     * (§6, ownership enforced at the query layer). This is the second line.
     */
    public static function viewerMaySee(?string $viewerId, string $subjectId): bool
    {
        return $viewerId !== null && $viewerId === $subjectId;
    }
}
