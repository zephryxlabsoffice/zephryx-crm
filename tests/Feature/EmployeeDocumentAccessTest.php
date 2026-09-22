<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HR viewing another employee's documents (review round Q16, answered
 * 2026-09-21).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE SECOND OF THE TWO PARTIES, NOT A NEW CAPABILITY
 *
 * ProfileController::documentFor's own header comment named this route before
 * it existed: "§6 says two parties reach these, the person and HR". This file
 * is the test for the second party — the person's own read is
 * ProfilePageTest, and the two are deliberately parallel: same ownership-in-
 * the-query shape, same audit obligation, different entity on the entry.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class EmployeeDocumentAccessTest extends TestCase
{
    /** The seeded person whose documents these tests read. */
    protected const SUBJECT = 'EMP002';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoWorkforce();
    }

    protected function signInAsSeeded(string $staffId): User
    {
        $user = User::where('user_id', $staffId)->firstOrFail();

        app(Rbac::class)->forget($user);
        $this->actingAs($user);

        return $user;
    }

    protected function subject(): Employee
    {
        return Employee::where('user_id', User::where('user_id', self::SUBJECT)->value('id'))->firstOrFail();
    }

    protected function theirDocument(): EmployeeDocument
    {
        return $this->subject()->documents()->firstOrFail();
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHO MAY
       ══════════════════════════════════════════════════════════════════════ */

    public function test_hr_may_view_and_download_a_colleagues_document(): void
    {
        $this->signInAsSeeded('EMP005'); // seeded HR
        $document = $this->theirDocument();

        $this->get('/employees/'.self::SUBJECT.'/documents/'.$document->reference.'/view')
            ->assertOk();

        $this->get('/employees/'.self::SUBJECT.'/documents/'.$document->reference.'/download')
            ->assertOk();
    }

    public function test_the_directory_permission_alone_does_not_reach_a_document(): void
    {
        // Same split as the identity reveal: looking somebody up is
        // employees.view, opening what they filed is employees.identifiers.
        // EMP001 is seeded as team_lead — holds the first, not the second.
        $this->signInAsSeeded('EMP001');
        $document = $this->theirDocument();

        $this->get('/employees/'.self::SUBJECT.'/documents/'.$document->reference.'/view')
            ->assertForbidden();

        $this->get('/employees/'.self::SUBJECT.'/documents/'.$document->reference.'/download')
            ->assertForbidden();
    }

    public function test_the_employee_record_page_shows_the_list_only_to_identifier_holders(): void
    {
        $document = $this->theirDocument();

        $this->signInAsSeeded('EMP005'); // seeded HR
        $this->get('/employees/'.self::SUBJECT)->assertSee($document->name, false);

        $this->signInAsSeeded('EMP001'); // seeded team_lead: employees.view, not employees.identifiers
        $this->get('/employees/'.self::SUBJECT)->assertDontSee($document->name, false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       SCOPE — THE QUERY, NOT A CHECK AFTER IT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_document_reference_belonging_to_someone_else_is_not_found(): void
    {
        // Asked for through EMP002's URL, filed under a different employee —
        // the lookup happens WITHIN the named employee's own set, so this is
        // the same 404 an imaginary reference gets, not a 403.
        $this->signInAsSeeded('EMP005');

        $other = Employee::where('id', '!=', $this->subject()->id)->firstOrFail();

        $theirs = EmployeeDocument::create([
            'reference' => 'DOC-9101',
            'employee_id' => $other->id,
            'name' => 'Somebody elses document.pdf',
            'kind' => 'identity',
            'path' => 'employees/'.$other->id.'/documents/whatever.pdf',
            'bytes' => 100,
        ]);

        $this->get('/employees/'.self::SUBJECT.'/documents/'.$theirs->reference.'/view')
            ->assertNotFound();
        $this->get('/employees/'.self::SUBJECT.'/documents/'.$theirs->reference.'/download')
            ->assertNotFound();
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT IT WRITES DOWN
       ══════════════════════════════════════════════════════════════════════ */

    public function test_viewing_is_audited_against_the_employee_not_the_actor(): void
    {
        $hr = $this->signInAsSeeded('EMP005');
        $document = $this->theirDocument();

        $this->get('/employees/'.self::SUBJECT.'/documents/'.$document->reference.'/view');

        $entry = DB::table('audit_log')
            ->where('action', AuditLog::EMPLOYEE_DOCUMENT_VIEWED)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('employee', $entry->entity_type);
        // The SUBJECT, not whoever clicked — "who looked at whose documents"
        // has to be answerable by reading this row alone.
        $this->assertSame(self::SUBJECT, $entry->entity_id);
        $this->assertSame($hr->id, $entry->actor_user_id);
        $this->assertStringContainsString($document->name, (string) $entry->after_json);
    }

    public function test_downloading_is_audited_as_its_own_action(): void
    {
        $this->signInAsSeeded('EMP005');
        $document = $this->theirDocument();

        $this->get('/employees/'.self::SUBJECT.'/documents/'.$document->reference.'/download');

        $this->assertSame(1, DB::table('audit_log')
            ->where('action', AuditLog::EMPLOYEE_DOCUMENT_DOWNLOADED)
            ->where('entity_id', self::SUBJECT)
            ->count());
    }

    public function test_a_document_that_does_not_exist_on_disk_is_not_found(): void
    {
        $this->signInAsSeeded('EMP005');

        $document = EmployeeDocument::create([
            'reference' => 'DOC-9102',
            'employee_id' => $this->subject()->id,
            'name' => 'Never actually stored.pdf',
            'kind' => 'other',
            'path' => 'employees/'.$this->subject()->id.'/documents/does-not-exist.pdf',
            'bytes' => 100,
        ]);

        $this->get('/employees/'.self::SUBJECT.'/documents/'.$document->reference.'/view')
            ->assertNotFound();
    }
}
