<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Profile\ProfileChanges;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HR's side of the change-request flow (2026-09-14).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE RECORD MOVES WHEN HR SAYS SO, AND NOT BEFORE
 *
 * The person fills the form in and a pending row is written. The live record is
 * untouched. HR checks it against the document handed in at the office and
 * applies it — at which point, and only then, the record changes.
 *
 * WHAT THE TESTS HERE ARE ACTUALLY GUARDING
 *
 * Three things, all of which would leave the flow looking finished while doing
 * nothing:
 *
 *   NOBODY DECIDES THEIR OWN. An HR person who could accept their own request
 *   has a form that saves, with two clicks instead of one.
 *
 *   A DECIDED REQUEST IS DECIDED. Two people open the queue, both press Apply,
 *   and the second must not re-apply a stale row over a record somebody has
 *   since corrected.
 *
 *   THE FIELD LIST IS CHECKED ON THE WAY OUT TOO. `changes` holds field NAMES
 *   that name columns to write. A row written before a field left the list must
 *   not apply it afterwards.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class ProfileChangeRequestTest extends TestCase
{
    protected Employee $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoWorkforce();

        $user = User::where('user_id', 'EMP002')->firstOrFail();
        $this->subject = Employee::where('user_id', $user->id)->firstOrFail();
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE QUEUE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_hr_sees_the_queue_and_the_directory_alone_does_not(): void
    {
        $this->request(['phone' => '+91 90000 11111']);

        $this->signInAsStaff(['employee', 'hr']);
        $this->get('/employees/requests')->assertOk()->assertSee('Change requests');

        // A Team Lead may look up a colleague. Deciding what their record says
        // is not the same act.
        $this->signInAsStaff(['employee', 'team_lead']);
        $this->get('/employees/requests')->assertForbidden();
    }

    public function test_the_queue_names_the_fields_and_not_the_values(): void
    {
        /*
         * A queue is a list read over somebody's shoulder in an open-plan
         * office. "Sunita asked to change her address" is all it has to say;
         * the address itself is on the page you open deliberately.
         */
        $this->request(['current_address' => "9 Secret Street\nKolkata"]);

        $this->signInAsStaff(['employee', 'hr']);

        $html = $this->get('/employees/requests')->assertOk()->getContent();

        $this->assertStringContainsString('Current address', $html);
        $this->assertStringNotContainsString('Secret Street', $html);
    }

    /* ══════════════════════════════════════════════════════════════════════
       APPLYING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_applying_moves_the_record(): void
    {
        $pending = $this->request(['phone' => '+91 90000 11111']);

        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees/requests/'.$pending->id.'/apply')->assertRedirect();

        $this->assertSame('+91 90000 11111', $this->subject->fresh()->profile->phone);
        $this->assertNotNull($pending->fresh()->applied_at);
    }

    public function test_applying_is_audited_against_the_person_and_names_no_value(): void
    {
        $pending = $this->request(['current_address' => "9 Secret Street\nKolkata"]);

        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees/requests/'.$pending->id.'/apply');

        $entry = DB::table('audit_log')
            ->where('action', AuditLog::PROFILE_CHANGE_APPLIED)
            ->first();

        $this->assertNotNull($entry);
        // Filed under the person whose record moved, not under HR.
        $this->assertSame('EMP002', $entry->entity_id);
        $this->assertStringNotContainsString('Secret Street', (string) $entry->after_json);
        $this->assertStringContainsString('Current address', (string) $entry->after_json);
    }

    public function test_a_request_already_decided_cannot_be_applied_again(): void
    {
        // Two people open the queue at once. The second must not re-apply a
        // stale row over a record somebody has since corrected by hand.
        $pending = $this->request(['phone' => '+91 90000 11111']);

        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees/requests/'.$pending->id.'/apply')->assertRedirect();

        $this->post('/employees/requests/'.$pending->id.'/apply')->assertNotFound();
    }

    public function test_nobody_decides_their_own(): void
    {
        /*
         * The rule the whole flow rests on. An HR person who could accept their
         * own request has a form that saves, with two clicks instead of one.
         */
        $hr = $this->signInAsStaff(['employee', 'hr']);
        $employee = Employee::create([
            'user_id' => $hr->id,
            'joined_on' => now()->subYear(),
        ]);

        $mine = ProfileChangeRequest::create([
            'employee_id' => $employee->id,
            'requested_by' => $hr->id,
            'changes' => ['phone' => '+91 90000 22222'],
        ]);

        $this->post('/employees/requests/'.$mine->id.'/apply')->assertForbidden();
        $this->post('/employees/requests/'.$mine->id.'/reject', ['reason' => 'No.'])->assertForbidden();

        $this->assertTrue($mine->fresh()->isPending());
    }

    public function test_a_field_that_has_left_the_requestable_list_is_not_applied(): void
    {
        /*
         * `changes` holds a field NAME that will be used to write to the column
         * it names — the same shape as the identifier reveal, and it gets the
         * same treatment: checked against the policy at BOTH ends. This writes
         * a row by hand carrying a field the policy does not name, which is
         * what a row stored before a decision changed would look like.
         */
        $forged = ProfileChangeRequest::create([
            'employee_id' => $this->subject->id,
            'requested_by' => $this->subject->user_id,
            'changes' => ['photo_path' => 'employees/1/photo/somebody-elses.png'],
        ]);

        $hr = $this->signInAsStaff(['employee', 'hr']);

        app(ProfileChanges::class)->apply($forged, $hr);

        $this->assertNull($this->subject->fresh()->profile?->photo_path);
    }

    /* ══════════════════════════════════════════════════════════════════════
       DECLINING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_declining_needs_a_reason_and_shows_it_to_them(): void
    {
        $pending = $this->request(['phone' => '+91 90000 11111']);

        $this->signInAsStaff(['employee', 'hr']);

        // A decline with no reason is a person told no by a screen, and the
        // first thing they do is go and ask somebody.
        $this->post('/employees/requests/'.$pending->id.'/reject', ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->post('/employees/requests/'.$pending->id.'/reject', [
            'reason' => 'No proof of address has been handed in yet.',
        ])->assertRedirect();

        $this->assertNotNull($pending->fresh()->rejected_at);

        // And the record did not move.
        $this->assertNotSame('+91 90000 11111', $this->subject->fresh()->profile?->phone);

        // The person reads the reason on their own page.
        $this->actingAsSubject();
        $this->get('/profile')->assertOk()->assertSee('No proof of address has been handed in yet.');
    }

    public function test_a_declined_request_is_kept(): void
    {
        // "They asked and it was declined" is the history an argument six
        // months later turns on.
        $pending = $this->request(['phone' => '+91 90000 11111']);

        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/employees/requests/'.$pending->id.'/reject', ['reason' => 'Bring the document in.']);

        $this->assertSame(1, ProfileChangeRequest::query()->count());
        $this->assertSame('declined', $pending->fresh()->outcome());
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * The subject asks for something, through the real form.
     *
     * @param  array<string, mixed>  $fields
     */
    protected function request(array $fields): ProfileChangeRequest
    {
        $this->actingAsSubject();

        $this->post('/profile', $fields)->assertRedirect('/profile');

        return ProfileChangeRequest::query()->pending()->firstOrFail();
    }

    protected function actingAsSubject(): void
    {
        $user = $this->subject->user;

        app(Rbac::class)->forget($user);
        $this->actingAs($user);
    }
}
