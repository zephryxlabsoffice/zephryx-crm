<?php

namespace Tests\Feature;

use App\Models\EmailChange;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\ProfileChangeRequest;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Support\LeaveDirectory;
use App\Support\LeavePolicy;
use App\Support\Profile\ProfileChanges;
use App\Support\ProfileDirectory;
use App\Support\ProfilePolicy;
use App\Support\Rbac\Rbac;
use Database\Seeders\AccountSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * My Profile, read from the database.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE VIEWER IS A SEEDED PERSON, NOT A BARE ACCOUNT
 *
 * These pages are the one place where "the signed-in person" has to be somebody
 * with an employment record, a department, a manager and some history — a fresh
 * User with no Employee behind it now gets a 403 (§2.1), which is correct and
 * would make every assertion below assert the error page.
 *
 * So the demo workforce is seeded and the session is Amit Verma, who is the
 * person the seeded profile, documents and reporting line belong to.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ProfilePageTest extends TestCase
{
    /** @var list<string> */
    protected const PAGES = ['/profile', '/profile/preferences', '/profile/password', '/profile/activity'];

    /** The seeded person these pages are about. */
    protected const VIEWER = 'EMP002';

    protected Employee $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoWorkforce();

        $this->viewer = $this->signInAsSeeded(self::VIEWER);
    }

    /**
     * Sign in as one of the seeded staff accounts.
     */
    protected function signInAsSeeded(string $staffId): Employee
    {
        $user = User::where('user_id', $staffId)->firstOrFail();

        app(Rbac::class)->forget($user);
        $this->actingAs($user);

        return Employee::where('user_id', $user->id)->firstOrFail();
    }

    public function test_the_four_pages_render(): void
    {
        $this->get('/profile')->assertOk()->assertSee('My Profile', false);
        $this->get('/profile/preferences')->assertOk()->assertSee('What the company sees', false);
        $this->get('/profile/password')->assertOk()->assertSee('Change your password', false);
        $this->get('/profile/activity')->assertOk()->assertSee('What has happened to your account', false);
    }

    public function test_somebody_with_no_employment_record_has_no_profile(): void
    {
        /*
         * A Mentor and the owner hold no Employee base (§2.1). They have no
         * profile — not an empty one — so this is a 403 rather than a page of
         * blanks claiming they have a record that says nothing.
         */
        $this->signInAsMentor();

        foreach (self::PAGES as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->post('/profile', ['phone' => '+91 90000 00000'])->assertForbidden();
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHO OWNS WHICH FIELD — THE DECISION THIS MODULE IS ABOUT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_nothing_hr_owns_is_an_input_on_any_profile_page(): void
    {
        // The handover had Full Name, Email Address and Date of Birth as
        // editable text boxes in this exact form. A person who can set their own
        // department can grant themselves whatever that department can see, and
        // one who can rename themselves detaches their own history from them.
        $locked = array_merge(
            ProfilePolicy::fieldsOwnedBy(ProfilePolicy::HR),
            ProfilePolicy::fieldsOwnedBy(ProfilePolicy::SYSTEM),
        );

        $this->assertNotEmpty($locked);

        foreach (self::PAGES as $url) {
            $html = $this->get($url)->getContent();

            foreach ($locked as $field) {
                $this->assertStringNotContainsString(
                    'name="'.$field.'"',
                    $html,
                    "{$field} is submittable on {$url} — it is not the person's to change",
                );
            }
        }
    }

    public function test_the_write_allow_list_holds_none_of_them(): void
    {
        // The rule that actually protects the record. `disabled` in the markup
        // is a rendering instruction; the browser is not where this lives.
        $editable = ProfilePolicy::selfEditable();

        foreach (['name', 'department', 'designation', 'reports_to', 'dob', 'role', 'employee_id', 'joined', 'email', 'last_login'] as $field) {
            $this->assertNotContains($field, $editable, "{$field} is in the self-editable allow-list");
        }
    }

    public function test_a_write_carrying_a_field_hr_owns_drops_it_rather_than_saving_it(): void
    {
        /*
         * The assertion the whole module rests on. `disabled` stops nobody, so
         * this posts the HR fields directly — a name, a department, a
         * designation, a date of birth and a reporting line — alongside a
         * legitimate change.
         *
         * The legitimate one becomes a REQUEST (2026-09-14) and nothing else
         * moves at all, with nothing erroring: a request that told somebody
         * which fields exist by refusing the ones that do would be worse than
         * one that quietly ignores them.
         */
        $user = $this->viewer->user;

        $wasName = $user->name;
        $wasDepartment = $this->viewer->department_id;
        $wasDesignation = $this->viewer->designation_id;
        $wasDob = $this->viewer->date_of_birth?->toDateString();
        $wasManager = $this->viewer->reports_to;

        $this->post('/profile', [
            'phone' => '+91 90000 12345',
            'name' => 'Somebody Else',
            'department' => 'Finance',
            'department_id' => 999,
            'designation_id' => 999,
            'dob' => '1970-01-01',
            'date_of_birth' => '1970-01-01',
            'reports_to' => 1,
            'email' => 'not.me@example.com',
            'role' => 'ceo',
            'user_id' => 'EMP999',
        ])->assertRedirect('/profile');

        // The phone is ASKED for, not written. That is the change of 2026-09-14
        // and the rest of this test is unchanged by it: an HR field must not
        // reach the record through either path.
        $this->assertNotSame('+91 90000 12345', $this->viewer->fresh()->profile?->phone);

        $changes = ProfileChangeRequest::query()->pending()->firstOrFail()->changes;

        $this->assertSame('+91 90000 12345', $changes['phone']);

        // And the point of the test: not one HR field reached the request
        // either. Being queued rather than saved is not a reason to be relaxed
        // about what may be queued.
        foreach (['name', 'department', 'department_id', 'designation_id', 'dob', 'date_of_birth', 'reports_to', 'email', 'role', 'user_id'] as $field) {
            $this->assertArrayNotHasKey($field, $changes);
        }

        $user->refresh();
        $this->viewer->refresh();

        $this->assertSame($wasName, $user->name);
        $this->assertNotSame('not.me@example.com', $user->email);
        $this->assertSame(self::VIEWER, $user->user_id);
        $this->assertSame($wasDepartment, $this->viewer->department_id);
        $this->assertSame($wasDesignation, $this->viewer->designation_id);
        $this->assertSame($wasDob, $this->viewer->date_of_birth?->toDateString());
        $this->assertSame($wasManager, $this->viewer->reports_to);
    }

    public function test_an_unknown_field_defaults_to_not_being_the_persons(): void
    {
        // The safe default for "who may change this" is somebody other than the
        // subject of it, so a field added without a decision is locked rather
        // than open.
        $this->assertSame(ProfilePolicy::HR, ProfilePolicy::ownerOf('salary_band'));
        $this->assertFalse(ProfilePolicy::isSelfEditable('salary_band'));
    }

    public function test_every_locked_field_says_why_and_offers_a_route_out(): void
    {
        // A read-only field with no way to correct it is a dead end, and the
        // first thing somebody does about it is ask.
        $html = $this->get('/profile')->getContent();

        foreach (ProfilePolicy::fieldsOwnedBy(ProfilePolicy::HR) as $field) {
            $this->assertNotSame('', ProfilePolicy::whyOf($field), "{$field} is locked with no reason given");
        }

        $this->assertStringContainsString('Not yours to change', $html);
        $this->assertStringContainsString(route(ProfilePolicy::correctionRoute()), $html);
    }

    public function test_the_things_that_are_the_persons_own_are_still_on_the_form(): void
    {
        /*
         * The counterpart to the locked fields: these are not read-only boxes
         * with an explanation, they are inputs somebody fills in. What changed
         * on 2026-09-14 is where the submission GOES, not whether the field is
         * theirs to fill in — so the page must still offer every one of them.
         */
        $html = $this->get('/profile')->getContent();

        foreach (['phone', 'current_address', 'permanent_address', 'emergency_name', 'emergency_phone', 'skills', 'languages'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html, "{$field} should be on the form");
            $this->assertTrue(ProfilePolicy::isRequestable($field));
        }
    }

    public function test_the_details_form_asks_rather_than_saves(): void
    {
        /*
         * The reversal, stated as a test. The form posts, the page says it went
         * to HR, and the record is exactly where it was.
         */
        $wasPhone = $this->viewer->profile?->phone;

        $this->post('/profile', $this->details())->assertRedirect('/profile');

        $this->assertSame($wasPhone, $this->viewer->fresh()->profile?->phone);

        $pending = ProfileChangeRequest::query()->pending()->firstOrFail();

        // Empties dropped and duplicates removed BEFORE the comparison:
        // "English, Hindi,  , English" is a typo, not four languages — and a
        // list compared as a raw string would look like a change every time.
        $this->assertSame(['English', 'Hindi'], $pending->changes['languages']);
        $this->assertSame(['Laravel', 'Testing'], $pending->changes['skills']);
        $this->assertSame("1 Somewhere Road\nKolkata", $pending->changes['current_address']);

        $this->assertStringContainsString('Waiting with HR', $this->pageBody('/profile'));
    }

    public function test_a_second_request_is_refused_while_one_is_waiting(): void
    {
        // Silently replacing the first would throw away something HR may
        // already have half-decided, and nobody would know.
        $this->post('/profile', $this->details())->assertRedirect('/profile');

        $this->post('/profile', $this->details(['phone' => '+91 90000 00000']))
            ->assertSessionHasErrors('pending');

        $this->assertSame(1, ProfileChangeRequest::query()->pending()->count());
    }

    public function test_a_submission_that_changes_nothing_asks_for_nothing(): void
    {
        /*
         * Somebody opens the form, changes their mind, and presses the button.
         * A pending row saying "no change" would sit in HR's queue forever, and
         * the person would be told they are waiting on something.
         */
        $this->post('/profile', $this->details())->assertRedirect('/profile');
        $this->post('/profile/requests/withdraw');

        // Apply it for real first, so the second submission genuinely differs
        // from nothing.
        $this->post('/profile', $this->details())->assertRedirect('/profile');
        $pending = ProfileChangeRequest::query()->pending()->firstOrFail();

        app(ProfileChanges::class)->apply($pending, $this->viewer->user);

        $this->post('/profile', $this->details())->assertRedirect('/profile');

        $this->assertSame(0, ProfileChangeRequest::query()->pending()->count());
    }

    public function test_a_request_can_be_withdrawn(): void
    {
        $this->post('/profile', $this->details())->assertRedirect('/profile');

        $this->post('/profile/requests/withdraw')->assertRedirect('/profile');

        $this->assertSame(0, ProfileChangeRequest::query()->pending()->count());
        // Kept, not deleted: "they asked and then changed their mind" is still
        // the history an argument later turns on.
        $this->assertSame(1, ProfileChangeRequest::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function details(array $overrides = []): array
    {
        return $overrides + [
            'phone' => '+91 98111 22334',
            'nationality' => 'Indian',
            'gender' => 'Prefer not to say',
            'languages' => 'English, Hindi,  , English',
            'skills' => 'Laravel, Testing',
            'current_address' => "1 Somewhere Road\nKolkata",
            'permanent_address' => "2 Elsewhere Lane\nHowrah",
            'emergency_name' => 'A Person',
            'emergency_relationship' => 'Sibling',
            'emergency_phone' => '+91 98111 00000',
        ];
    }

    public function test_a_field_the_policy_offers_no_option_for_is_refused(): void
    {
        // Gender and marital status are validated against the list the policy
        // offers, so the stored value is always one the page can draw back.
        $this->post('/profile', ['gender' => 'Whatever I typed'])
            ->assertSessionHasErrors('gender');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE CREDENTIALS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_email_is_never_a_text_box_on_the_details_form(): void
    {
        /*
         * It is the login identifier (§4.1). A field that writes it straight to
         * the record is an account-takeover primitive.
         *
         * The email-change form has an input, and it is `new_email` on a route
         * that mails both addresses — deliberately not `email`, and deliberately
         * not on the details form under one Save button.
         */
        foreach (self::PAGES as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<input[^>]*name="email"/i', $html), "an email input on {$url}");
        }

        $this->assertSame(ProfilePolicy::VERIFIED, ProfilePolicy::ownerOf('email'));
    }

    public function test_changing_the_email_needs_the_current_password(): void
    {
        $this->post('/profile/email', [
            'new_email' => 'new.address@zephryxlabs.com',
            'current_password' => 'not the password',
        ])->assertSessionHasErrors('current_password');

        $this->assertDatabaseCount('email_changes', 0);
    }

    public function test_the_address_does_not_move_until_both_ends_confirm(): void
    {
        /*
         * The property this flow exists for. Confirming only the old address
         * leaves the account signing in with the address it always had — which
         * is what stops a typo, or somebody's borrowed session, locking an
         * account to a mailbox its owner cannot read.
         */
        $user = $this->viewer->user;
        $was = $user->email;

        $this->post('/profile/email', [
            'new_email' => 'new.address@zephryxlabs.com',
            'current_password' => AccountSeeder::DEV_PASSWORD,
        ])->assertRedirect();

        $change = EmailChange::firstOrFail();

        $this->assertSame($was, $user->fresh()->email, 'the address moved before anybody confirmed');

        // One half.
        $this->get('/profile/email/confirm/'.$this->tokenFor($change, 'old'))->assertRedirect();
        $this->assertSame($was, $user->fresh()->email, 'one confirmation was enough, and it must not be');

        // The other.
        $this->get('/profile/email/confirm/'.$this->tokenFor($change, 'new'))->assertRedirect();
        $this->assertSame('new.address@zephryxlabs.com', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_changing_the_password_asks_for_the_current_one(): void
    {
        // A borrowed unlocked laptop is the whole threat, and this field is the
        // only thing standing in front of it.
        $html = $this->get('/profile/password')->getContent();

        $this->assertStringContainsString('name="current_password"', $html);
        $this->assertStringContainsString('name="password"', $html);
        $this->assertStringContainsString('name="password_confirmation"', $html);

        $this->post('/profile/password', [
            'current_password' => 'not the password',
            'password' => 'a perfectly fine passphrase',
            'password_confirmation' => 'a perfectly fine passphrase',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_a_correct_current_password_changes_it(): void
    {
        $this->post('/profile/password', [
            'current_password' => AccountSeeder::DEV_PASSWORD,
            'password' => 'a perfectly fine passphrase',
            'password_confirmation' => 'a perfectly fine passphrase',
        ])->assertRedirect('/profile/password');

        $user = $this->viewer->user->fresh();

        $this->assertTrue(Hash::check('a perfectly fine passphrase', $user->password));
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_the_password_policy_is_enforced_and_not_only_stated(): void
    {
        // §4.7: twelve characters and a blocklist. No composition rules.
        $this->post('/profile/password', [
            'current_password' => AccountSeeder::DEV_PASSWORD,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->post('/profile/password', [
            'current_password' => AccountSeeder::DEV_PASSWORD,
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ])->assertSessionHasErrors('password');
    }

    public function test_the_password_policy_is_stated_including_what_it_does_not_ask_for(): void
    {
        // A password field that does not demand a capital and a symbol looks
        // broken to anybody used to ones that do.
        $response = $this->get('/profile/password');

        $response->assertSee('At least twelve characters', false);
        $response->assertSee('No composition rules', false);
        $response->assertSee('No expiry', false);
    }

    public function test_no_page_nags_about_password_age(): void
    {
        // §4.7 is explicit that there is no forced rotation. Reporting the age
        // is a fact; asking somebody to change a fine password is a nag that
        // produces weaker ones.
        foreach (self::PAGES as $url) {
            $html = $this->get($url)->getContent();

            $this->assertStringNotContainsStringIgnoringCase('password expires', $html);
            $this->assertStringNotContainsStringIgnoringCase('time to change your password', $html);
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       IT IS ALWAYS YOUR OWN PROFILE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_no_profile_route_takes_an_employee(): void
    {
        /*
         * Resolved from the session, so there is no identifier to change to
         * somebody else's. The two parameters that exist are a document
         * reference — scoped to the signed-in person's own inside the query —
         * and an email-change token, which carries its own authority.
         */
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'profile')) {
                continue;
            }

            foreach ($route->parameterNames() as $parameter) {
                $this->assertContains($parameter, ['document', 'token'], "profile route {$route->uri()} takes {$parameter}");
            }
        }
    }

    public function test_somebody_elses_document_is_not_found(): void
    {
        /*
         * Not forbidden — not found. The lookup happens WITHIN this person's
         * own documents, so there is no branch that could be written the wrong
         * way round, and no reference that tells somebody a document exists.
         */
        $colleague = Employee::where('id', '!=', $this->viewer->id)->firstOrFail();

        $theirs = EmployeeDocument::create([
            'reference' => 'DOC-9001',
            'employee_id' => $colleague->id,
            'name' => 'Their PAN card.pdf',
            'kind' => 'identity',
            'path' => 'employees/'.$colleague->id.'/documents/whatever.pdf',
            'bytes' => 100,
        ]);

        $this->get('/profile/documents/'.$theirs->reference)->assertNotFound();
    }

    public function test_downloading_your_own_document_works_and_is_logged(): void
    {
        $document = $this->viewer->documents()->firstOrFail();

        $this->get('/profile/documents/'.$document->reference)->assertOk();

        $this->assertDatabaseHas('audit_log', [
            'action' => 'profile.document_downloaded',
            'actor_user_id' => $this->viewer->user_id,
        ]);
    }

    public function test_uploading_a_document_stores_it_on_drive(): void
    {
        // Documents are Drive-backed going forward — see DocumentStore's
        // class header.
        $this->connectGoogleDrive();
        $this->fakeDriveUpload();

        $this->post('/profile/documents', [
            'document' => UploadedFile::fake()->create('resume.pdf', 30, 'application/pdf'),
            'kind' => 'resume',
        ])->assertRedirect();

        $document = EmployeeDocument::where('employee_id', $this->viewer->id)
            ->where('name', 'resume.pdf')
            ->firstOrFail();

        $this->assertStringStartsWith('drive:', $document->path);
    }

    public function test_a_document_upload_that_cannot_reach_drive_is_a_validation_error_not_a_500(): void
    {
        // No connectGoogleDrive() — the singleton row does not exist, so
        // DocumentStore::put() throws before anything is saved.
        $this->post('/profile/documents', [
            'document' => UploadedFile::fake()->create('resume.pdf', 30, 'application/pdf'),
            'kind' => 'resume',
        ])->assertSessionHasErrors('document');
    }

    public function test_the_activity_log_is_the_viewers_own_only(): void
    {
        /*
         * Scoped by entity as well as by actor, so it carries what happened TO
         * this account — including a sign-in they did not make, which is the
         * entry the page exists for. What it must never carry is a colleague's.
         */
        $colleague = $this->signInAsSeeded('EMP004');

        $this->post('/profile', ['phone' => '+91 90000 44444'])->assertRedirect();

        $this->signInAsSeeded(self::VIEWER);
        $this->post('/profile', ['phone' => '+91 90000 22222'])->assertRedirect();

        $mine = ProfileDirectory::activity($this->viewer->fresh());
        $theirs = ProfileDirectory::activity($colleague->fresh());

        $this->assertNotEmpty($mine);
        $this->assertNotEmpty($theirs);
        $this->assertStringContainsString('profile.updated', 'profile.updated');

        $this->assertSame(1, $mine->where('kind', 'profile_updated')->count());
        $this->assertSame(1, $theirs->where('kind', 'profile_updated')->count());
    }

    public function test_the_activity_log_claims_no_location(): void
    {
        // The handover's read "New login from Kolkata, IN" — IP geolocation that
        // does not exist and was never decided. This is the screen somebody
        // checks when they think their account has been used by someone else,
        // and a wrong city sends them chasing nothing.
        $html = $this->get('/profile/activity')->getContent();

        $this->assertStringNotContainsStringIgnoringCase('from Kolkata', $html);
        $this->assertStringNotContainsStringIgnoringCase('IP address', $html);
    }

    /* ══════════════════════════════════════════════════════════════════════
       PERSONAL DATA
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_birth_year_never_reaches_the_page(): void
    {
        // The same rule the birthday board holds to — a day and a month, never
        // the year. It applies on a person's own profile too, because a
        // screenshot of this page is still a screenshot.
        $dob = $this->viewer->date_of_birth;

        $this->assertNotNull($dob, 'the seeded viewer has no date of birth, so this proves nothing');

        foreach (self::PAGES as $url) {
            $this->assertStringNotContainsString(
                $dob->format('d M Y'),
                $this->get($url)->getContent(),
                "a full date of birth on {$url}",
            );
        }

        $this->assertStringContainsString($dob->format('d F'), $this->get('/profile')->getContent());
    }

    public function test_documents_are_reached_through_a_route_and_never_a_static_path(): void
    {
        // A PAN or Aadhaar scan at a guessable path under the webroot is a link
        // that works for anyone who tries it, forever, with no session involved.
        $html = $this->get('/profile')->getContent();

        $documents = $this->viewer->documents;

        $this->assertNotEmpty($documents, 'the viewer has no documents, so this proves nothing');

        foreach ($documents as $document) {
            $this->assertStringContainsString(
                route('profile.documents.download', ['document' => $document->reference]),
                $html,
            );

            // And the stored path is nowhere on the page, in any form.
            $this->assertStringNotContainsString($document->path, $html);
        }

        $this->assertStringNotContainsString('/storage/', $html);
        $this->assertStringContainsString('every download is logged', $html);
    }

    public function test_no_document_is_previewed_or_thumbnailed(): void
    {
        // A thumbnail is the document, smaller.
        $html = $this->get('/profile')->getContent();

        $this->assertSame(0, preg_match_all('/<img[^>]*document/i', $html));
        $this->assertSame(0, preg_match_all('/<iframe/i', $html));
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE MILESTONE OPT-OUT FINALLY HAS A SURFACE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_milestone_opt_out_is_reachable(): void
    {
        // Announcements decided (2026-08-28) that anyone may opt out of their
        // own birthday post, and `announce_milestones` has existed on the
        // employee record since with nowhere to set it. A per-person opt-out
        // nobody can reach is not an opt-out; it is a column.
        $response = $this->get('/profile/preferences');

        $response->assertSee('name="announce_milestones"', false);
        $response->assertSee('Announce my birthday and work anniversary', false);
        $this->assertTrue(ProfilePolicy::isSelfEditable('announce_milestones'));
    }

    public function test_the_opt_out_can_actually_be_turned_off(): void
    {
        /*
         * An unchecked checkbox sends nothing at all, so the write reads the
         * ABSENCE of the key as off. Written the obvious way — `?? true` — the
         * opt-out would be unsettable, which turns a per-person opt-out back
         * into a column that is always on.
         */
        $this->assertTrue($this->viewer->announce_milestones);

        $this->post('/profile/preferences', [])->assertRedirect('/profile/preferences');

        $this->assertFalse($this->viewer->fresh()->announce_milestones);

        $this->post('/profile/preferences', ['announce_milestones' => 1])->assertRedirect();

        $this->assertTrue($this->viewer->fresh()->announce_milestones);
    }

    public function test_the_opt_out_reflects_the_stored_value(): void
    {
        $html = $this->get('/profile/preferences')->getContent();

        // Matched on the input tag rather than an exact attribute string, so the
        // test is about the state being reflected and not about the order Blade
        // happens to write attributes in.
        preg_match('/<input[^>]*name="announce_milestones"[^>]*>/i', $html, $input);

        $this->assertNotEmpty($input, 'the opt-out is not on the page at all');
        $this->assertSame(
            $this->viewer->announce_milestones,
            str_contains($input[0], 'checked'),
            'the checkbox does not reflect the stored value',
        );
    }

    public function test_the_notification_switches_are_saved(): void
    {
        $this->post('/profile/preferences', ['notify_tasks' => 1])->assertRedirect();

        $profile = $this->viewer->fresh()->profile;

        $this->assertTrue($profile->notify_tasks);
        $this->assertFalse($profile->notify_tickets);
    }

    public function test_there_is_no_switch_for_emailing_notifications(): void
    {
        /*
         * Nothing in this application emails a notification. A switch that
         * turns on a thing that does not exist is a promise, and the person who
         * sets it stops watching the bell.
         */
        $html = $this->get('/profile/preferences')->getContent();

        $this->assertStringNotContainsString('name="notify_email"', $html);
        $this->assertStringNotContainsStringIgnoringCase('Also send these by email', $html);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE SUMMARY IS READ, NOT INVENTED
       ══════════════════════════════════════════════════════════════════════ */

    public function test_every_summary_figure_comes_from_the_module_that_owns_it(): void
    {
        // The handover hardcoded 15 / 128 / 42 / "22 of 22" / 12 days. A summary
        // that disagrees with the page it summarises is worse than no summary.
        $summary = collect(ProfileDirectory::summary($this->viewer))->keyBy('label');

        $this->assertSame(
            (string) Project::query()->forEmployee($this->viewer)->count(),
            $summary['Projects you are on']['value'],
        );

        $this->assertSame(
            (string) Ticket::query()->where('raised_by', $this->viewer->id)->count(),
            $summary['Tickets you raised']['value'],
        );

        $balance = LeavePolicy::balance(LeaveDirectory::forEmployee($this->viewer));

        $this->assertSame(
            $balance['remaining'].' of '.$balance['entitlement'].' days',
            $summary['Leave left this year']['value'],
        );
    }

    public function test_a_summary_figure_moves_when_the_module_behind_it_does(): void
    {
        /*
         * The stronger version of the test above: reading the same query twice
         * would agree with itself even if both were wrong. This changes the
         * underlying record and asserts the tile follows.
         */
        $before = collect(ProfileDirectory::summary($this->viewer))->firstWhere('label', 'Tickets you raised')['value'];

        Ticket::create([
            'reference' => 'TKT-2026-950',
            'type' => 'internal',
            'subject' => 'Something to report',
            'description' => 'Anything.',
            'raised_by' => $this->viewer->id,
            'status' => 'unassigned',
        ]);

        $after = collect(ProfileDirectory::summary($this->viewer))->firstWhere('label', 'Tickets you raised')['value'];

        $this->assertSame((int) $before + 1, (int) $after);
    }

    public function test_every_summary_row_links_to_the_page_it_came_from(): void
    {
        // A number somebody disputes should be one click from the page that
        // produced it — which is also what stops this card becoming a second
        // source of truth.
        $html = $this->get('/profile')->getContent();

        foreach (ProfileDirectory::summary($this->viewer) as $row) {
            $this->assertStringContainsString(route($row['route']), $html);
        }
    }

    public function test_the_handovers_invented_figures_are_gone(): void
    {
        $response = $this->get('/profile');

        $response->assertDontSee('>128<', false);
        $response->assertDontSee('22 / 22', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE USUAL GUARDS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_write_routes_exist_so_the_forms_are_real(): void
    {
        foreach ([
            'profile.update', 'profile.preferences.update', 'profile.password.update',
            'profile.email.change', 'profile.email.confirm', 'profile.photo',
            'profile.documents.store', 'profile.documents.download',
        ] as $name) {
            $this->assertTrue(app('router')->has($name), "{$name} is missing");
        }
    }

    public function test_no_route_writes_a_field_hr_owns(): void
    {
        // There is no `profile.role`, no `profile.department`. A route that let
        // somebody set their own designation would make the record meaningless.
        foreach (ProfilePolicy::fieldsOwnedBy(ProfilePolicy::HR) as $field) {
            $this->assertFalse(app('router')->has('profile.'.$field));
        }
    }

    public function test_the_tabs_are_links_with_their_own_urls(): void
    {
        // The handover's were <button>s switched by an inline <script>, which
        // our CSP blocks — they would not have switched at all.
        $html = $this->get('/profile')->getContent();

        foreach (['profile.show', 'profile.preferences', 'profile.password', 'profile.activity'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $html);
        }
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        foreach (self::PAGES as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), "inline <style> in {$url}");
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), "inline style attribute in {$url}");
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), "inline event handler in {$url}");
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), "inline <script> in {$url}");
        }
    }

    public function test_the_sidebar_marks_the_profile_as_current(): void
    {
        foreach (self::PAGES as $url) {
            $this->assertSame(
                1,
                substr_count($this->get($url)->getContent(), 'class="sb-link active"'),
                "sidebar current marker wrong on {$url}",
            );
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * The plaintext token for one half of a change.
     *
     * The tokens are only ever returned to the controller, so a test cannot
     * read one back out of the row — it is stored hashed, which is the point.
     * This re-issues by writing a known hash instead, which exercises the same
     * lookup `confirm()` does without weakening the storage to make a test
     * convenient.
     */
    protected function tokenFor(EmailChange $change, string $half): string
    {
        $token = 'test-token-'.$half.'-'.$change->id;

        $change->forceFill([$half.'_token_hash' => hash('sha256', $token)])->save();

        return $token;
    }
}
