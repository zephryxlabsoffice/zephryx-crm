<?php

namespace App\Support;

/**
 * Who owns which field on a person's profile.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE ONE DECISION THIS MODULE IS ABOUT
 *
 * Decided 2026-09-03. A profile page is two things wearing one layout: a form
 * for what a person may change about themselves, and a record of what the
 * company holds about them. Every real problem with a profile screen comes from
 * blurring those.
 *
 * The handover blurred them completely. It put Full Name, Email Address and
 * Date of Birth in editable text boxes next to Department and Employee ID shown
 * as read-only facts, with one "Save Changes" button under all of it. Three of
 * those four editable fields must not be self-service:
 *
 *   NAME is on every task, ticket comment, payslip, approval and audit entry
 *   this system has ever written. It is how colleagues find each other and how
 *   a record is attributed. Somebody quietly renaming themselves detaches their
 *   own history from them, and there is no honest way to undo it afterwards.
 *
 *   EMAIL IS THE LOGIN IDENTIFIER (§4.1). A plain text box that writes it
 *   straight to the record is an account-takeover primitive: anyone with a
 *   borrowed session points the account at their own address, and the real
 *   owner is locked out of a system that no longer knows how to reach them. It
 *   is still the person's to change — but through a flow that proves they hold
 *   both addresses, never through a field with a Save button.
 *
 *   DATE OF BIRTH drives the birthday announcements (App\Support\Milestones)
 *   and is HR-held employment data. It is not a preference.
 *
 * What IS the person's own is everything that describes them rather than
 * identifies them, plus everything about how they are contacted and how the
 * application behaves for them. That list is genuinely long, and it is what
 * makes the page worth having.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * AND THEN HALF OF THAT LIST MOVED (2026-09-14)
 *
 * The paragraph above ended "nobody should raise a ticket to correct their own
 * phone number", and the owner has reversed it on purpose. The profile is the
 * COMPANY'S record of a person. It is corrected against documents handed in at
 * the office, not on the strength of a form.
 *
 * So the descriptive fields, the contact details, the emergency contact and the
 * photo are now REQUESTED: the person fills the form in, a pending row is
 * written, the live record does not move, and HR applies it when the paperwork
 * arrives. What did NOT move is the preferences — theme, density, sidebar and
 * the two notification toggles still save on the spot, because they are
 * settings rather than a record of anything, and an approval queue full of
 * dark-mode requests would bury the ones that matter.
 *
 * The reversal is not a loosening or a tightening of this class's rule; it is
 * the same rule applied to a different answer about who owns a fact. Which is
 * why it is a new owner constant and not a special case somewhere else.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THIS IS A TABLE AND NOT AN `@if` IN A BLADE
 *
 * The rule has to be inspectable in one place, testable without rendering a
 * page, and identical on the form and on the write that accepts it. Scattered
 * across templates it becomes a rule nobody can state — and the failure is
 * silent, because a field wrongly left editable looks exactly like one that is
 * meant to be.
 *
 * The backend validates against `selfEditable()` and ignores everything else in
 * the request. A field that is read-only in the markup is not read-only; it is
 * read-only in the markup.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ProfilePolicy
{
    /** Type it, save it. */
    public const SELF = 'self';

    /**
     * Theirs to correct, but not on their own say-so (2026-09-14).
     *
     * They fill the field in, the request goes to HR, they bring the document
     * to the office, and HR applies it. The live record does not move in the
     * meantime. See App\Support\Profile\ProfileChanges and the head of this
     * class for the reversal this represents.
     */
    public const REQUESTED = 'requested';

    /** Theirs to change, but only by proving it — never a text box. */
    public const VERIFIED = 'verified';

    /** Employment data. Read-only here; correcting it is a request to HR. */
    public const HR = 'hr';

    /** Recorded by the application. Nobody types it, including HR. */
    public const SYSTEM = 'system';

    /**
     * @var array<string, array{0: string, 1: string, 2: string}> owner, label, why
     */
    protected const FIELDS = [
        // ── the company's record of who somebody is ──
        'name' => [self::HR, 'Full name', 'It is on every task, payslip and audit entry you appear in.'],
        'employee_id' => [self::SYSTEM, 'Employee ID', 'Issued when your account was created. It never changes.'],
        'department' => [self::HR, 'Department', 'Set by HR — it decides what you can see across the application.'],
        'designation' => [self::HR, 'Designation', 'Set by HR, alongside your role and reporting line.'],
        'reports_to' => [self::HR, 'Reporting to', 'Set by HR.'],
        'joined' => [self::SYSTEM, 'Joined on', 'Your start date, from your employment record.'],
        'dob' => [self::HR, 'Date of birth', 'Held by HR. It is what the birthday board reads, and only ever as a day and a month.'],
        'role' => [self::HR, 'Role', 'What you are permitted to do. Only the owner changes this.'],

        // ── the login ──
        'email' => [self::VERIFIED, 'Email address', 'This is how you sign in, so changing it has to be confirmed from both addresses.'],
        'password' => [self::VERIFIED, 'Password', 'Changing it needs your current one.'],

        // ── recorded by the application ──
        'last_login' => [self::SYSTEM, 'Last sign-in', 'Recorded automatically. If it looks wrong, tell the owner.'],
        'email_verified' => [self::SYSTEM, 'Email verified', 'Set when you confirmed your address.'],

        /*
         * ── the person's, corrected against documents ──
         *
         * Reversed from SELF on 2026-09-14. Every one of these describes a fact
         * about somebody that the company holds a paper record of, and the
         * paper record is what settles it.
         *
         * The `why` line is empty on all of them because they are not LOCKED —
         * the page renders them as ordinary inputs, and the explanation belongs
         * once above the form rather than repeated twelve times beside fields
         * the person is being invited to fill in.
         */
        'phone' => [self::REQUESTED, 'Phone number', ''],
        'current_address' => [self::REQUESTED, 'Current address', ''],
        'permanent_address' => [self::REQUESTED, 'Permanent address', ''],
        'gender' => [self::REQUESTED, 'Gender', ''],
        'marital_status' => [self::REQUESTED, 'Marital status', ''],
        'nationality' => [self::REQUESTED, 'Nationality', ''],
        'languages' => [self::REQUESTED, 'Languages known', ''],
        'skills' => [self::REQUESTED, 'Skills', ''],
        'photo' => [self::REQUESTED, 'Profile photo', ''],
        'emergency_name' => [self::REQUESTED, 'Emergency contact name', ''],
        'emergency_relationship' => [self::REQUESTED, 'Relationship', ''],
        'emergency_phone' => [self::REQUESTED, 'Emergency contact number', ''],

        /*
         * ── preferences, and they still save instantly ──
         *
         * Deliberately NOT moved with the rest (2026-09-14). These are settings,
         * not a record of anything: nothing is checked against a document,
         * nothing downstream depends on them being true, and an approval queue
         * full of dark-mode requests would bury the ones that matter.
         */
        'announce_milestones' => [self::SELF, 'Announce my birthday and work anniversary', ''],
        'theme' => [self::SELF, 'Appearance', ''],
        'density' => [self::SELF, 'Density', ''],
        'sidebar' => [self::SELF, 'Sidebar', ''],
    ];

    public static function ownerOf(string $field): string
    {
        // An unrecognised field is HR's, not the person's. The safe default for
        // "who may change this" is somebody other than the subject of it.
        return self::FIELDS[$field][0] ?? self::HR;
    }

    public static function labelOf(string $field): string
    {
        return self::FIELDS[$field][1] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Why a field is not the person's to change, in words they can act on.
     *
     * Shown next to every locked field. "Read-only" with no explanation reads
     * as the application being unfinished, and the first thing somebody does
     * about it is ask, which is the support request this text exists to save.
     */
    public static function whyOf(string $field): string
    {
        return self::FIELDS[$field][2] ?? '';
    }

    public static function isSelfEditable(string $field): bool
    {
        return self::ownerOf($field) === self::SELF;
    }

    public static function isRequestable(string $field): bool
    {
        return self::ownerOf($field) === self::REQUESTED;
    }

    /**
     * The allow-list a CHANGE REQUEST validates against.
     *
     * The counterpart of `selfEditable()`, and it does the same job for the
     * other half of the form: the submission validates against this and ignores
     * every other key, and applying a stored request checks the field against
     * it a second time. Two checks because the value crosses a table boundary
     * in between — a field name written into a JSON column and later used to
     * name a column to write is only safe while both ends agree on the list.
     *
     * `photo` is in here and has no rule alongside the others: it is a file, it
     * arrives on its own multipart form, and what validates it is
     * App\Support\Images\PhotoIntake. It is listed so the policy remains the
     * single statement of what may be requested.
     *
     * @return list<string>
     */
    public static function requestable(): array
    {
        return array_keys(array_filter(
            self::FIELDS,
            fn (array $field) => $field[0] === self::REQUESTED
        ));
    }

    /**
     * The allow-list a write validates against.
     *
     * The backend takes THIS and ignores every other key in the request. A form
     * that renders a field as disabled has not protected anything — `disabled`
     * is a rendering instruction, and the browser is not where the rule lives.
     *
     * @return list<string>
     */
    public static function selfEditable(): array
    {
        return array_keys(array_filter(
            self::FIELDS,
            fn (array $field) => $field[0] === self::SELF
        ));
    }

    /**
     * @return list<string>
     */
    public static function fieldsOwnedBy(string $owner): array
    {
        return array_keys(array_filter(
            self::FIELDS,
            fn (array $field) => $field[0] === $owner
        ));
    }

    /**
     * Options for the descriptive fields.
     *
     * `null` is a real answer on every one of them and is listed first. A person
     * who does not want to state their gender or marital status must not have to
     * pick the least wrong option from a list somebody else wrote — and a
     * required field here would collect worse data than an optional one.
     *
     * @return array<string, list<string>>
     */
    public static function options(): array
    {
        return [
            'gender' => ['Prefer not to say', 'Female', 'Male', 'Other'],
            'marital_status' => ['Prefer not to say', 'Single', 'Married', 'Divorced', 'Widowed'],
        ];
    }

    /**
     * What HR is asked for when a locked field is wrong.
     *
     * A read-only field with no route to changing it is a dead end. This module
     * does not own the fix — HR does — so it names the surface that does rather
     * than growing a request form of its own.
     */
    public static function correctionRoute(): string
    {
        return 'tickets.create';
    }
}
