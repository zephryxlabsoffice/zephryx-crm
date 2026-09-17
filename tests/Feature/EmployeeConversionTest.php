<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeBanking;
use App\Models\EmployeeProfile;
use App\Models\MasterDataItem;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Documents\DocumentStore;
use App\Support\IdProof;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Taking an intern on permanently (decided 2026-09-11).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * TWO RECORDS, AND THE REASON IT IS NOT ONE EDITED COLUMN
 *
 * Flipping `employment_type` on the existing row is one line and wrong in four
 * ways at once: the staff ID keeps the intern digit, a year of intern leave
 * carries into a full-time balance, attendance already recorded silently
 * becomes full-time attendance, and nothing anywhere says when it happened.
 *
 * So a conversion issues a new identifier and a new account, closes the old
 * record without deleting it, and threads the two together. Everything HR typed
 * comes across — the owner asked for a button, not a second pass at the form —
 * and everything that is HISTORY stays where it was earned.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class EmployeeConversionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT IT PRODUCES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_it_issues_a_full_time_identifier_and_closes_the_old_record(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $intern = $this->intern();
        $oldStaffId = $intern->user->user_id;

        $this->post('/employees/'.$oldStaffId.'/convert')->assertRedirect();

        $fresh = Employee::where('converted_from_id', $intern->id)->with('user')->firstOrFail();

        // The digit carries the engagement: 1 is full-time, 2 is an intern.
        $this->assertStringStartsWith('ZEPH', $oldStaffId);
        $this->assertSame(Employee::FULL_TIME, $fresh->employment_type);
        $this->assertNotSame($oldStaffId, $fresh->user->user_id);

        // The old one is closed, not deleted. Everything recorded against it
        // stays filed under the identifier it was recorded against.
        $intern->refresh();
        $this->assertSame('inactive', $intern->user->status);
        $this->assertSame(Employee::INTERN, $intern->employment_type);
    }

    public function test_the_work_email_moves_and_the_old_account_keeps_a_tombstone(): void
    {
        /*
         * The email is the sign-in identifier and is unique across every
         * account, so both rows cannot hold it. The closed one keeps an address
         * built from the one it had — it records which address signed in there,
         * cannot collide, and is a valid shape so nothing downstream chokes.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $intern = $this->intern();
        $oldStaffId = $intern->user->user_id;

        $this->post('/employees/'.$oldStaffId.'/convert');

        $fresh = Employee::where('converted_from_id', $intern->id)->with('user')->firstOrFail();

        $this->assertSame('new.intern@example.test', $fresh->user->email);
        $this->assertSame('new.intern+'.$oldStaffId.'@example.test', $intern->refresh()->user->email);
    }

    public function test_the_new_account_is_invited_and_nobody_types_a_password(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $intern = $this->intern();
        $this->post('/employees/'.$intern->user->user_id.'/convert');

        Mail::assertSent(\App\Mail\AccountInviteMail::class);
    }

    public function test_the_leave_year_starts_again_from_the_conversion(): void
    {
        /*
         * The point of the whole shape. The leave year runs from each person's
         * own joining month and the balance starts fresh — both read
         * `joined_on`, so carrying the intern's date over would hand them a
         * year of accrual they have not earned on this engagement.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $intern = $this->intern();
        $this->post('/employees/'.$intern->user->user_id.'/convert');

        $fresh = Employee::where('converted_from_id', $intern->id)->firstOrFail();

        $this->assertSame(Carbon::now()->toDateString(), $fresh->joined_on->toDateString());
        $this->assertTrue($intern->refresh()->joined_on->lt(Carbon::now()->subMonths(5)));
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT COMES ACROSS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_identity_and_bank_details_are_not_retyped(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $intern = $this->intern();
        $this->post('/employees/'.$intern->user->user_id.'/convert');

        $fresh = Employee::where('converted_from_id', $intern->id)->firstOrFail();
        $banking = EmployeeBanking::where('employee_id', $fresh->id)->firstOrFail();

        // Read back through the cast, which is the whole point: the values are
        // copied as VALUES, not as ciphertext moved between rows.
        $this->assertSame('123456781234', $banking->id_proof_number);
        $this->assertSame(IdProof::AADHAAR, $banking->id_proof_type);
        $this->assertSame('50100234567890', $banking->account_number);
    }

    public function test_the_profile_comes_across_and_the_photo_is_a_separate_file(): void
    {
        /*
         * Two rows pointing at one file is a photo that vanishes from the
         * closed record the first time somebody replaces it on the new one.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $intern = $this->intern();

        $stored = app(DocumentStore::class)->putBytes(
            'employees/'.$intern->id.'/photo',
            'png',
            'not-really-a-png-but-bytes-all-the-same',
        );

        // updateOrCreate: the add form already wrote a row for the addresses,
        // which is the normal state for anybody HR has entered properly.
        EmployeeProfile::updateOrCreate(['employee_id' => $intern->id], [
            'phone' => '+91 90000 33333',
            'current_address' => "1 Somewhere Road\nKolkata",
            'photo_path' => $stored['path'],
        ]);

        $this->post('/employees/'.$intern->user->user_id.'/convert');

        $fresh = Employee::where('converted_from_id', $intern->id)->firstOrFail();
        $profile = EmployeeProfile::where('employee_id', $fresh->id)->firstOrFail();

        $this->assertSame('+91 90000 33333', $profile->phone);
        $this->assertSame("1 Somewhere Road\nKolkata", $profile->current_address);
        // The personal address especially: it is how somebody is reached after
        // they leave, and a conversion is the moment it would be lost.
        $this->assertSame('the.intern@personal.test', $profile->personal_email);

        $this->assertNotSame($stored['path'], $profile->photo_path);
        $this->assertTrue(app(DocumentStore::class)->exists($profile->photo_path));
        // And the closed record still has its own.
        $this->assertTrue(app(DocumentStore::class)->exists($stored['path']));
    }

    public function test_the_salary_is_deliberately_not_carried(): void
    {
        /*
         * A stipend and a full-time breakdown are not the same shape, and a
         * conversion comes with a new number. The new record shows nothing on
         * file until HR sets it, which is a chaseable absence rather than a
         * silent zero.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $intern = $this->intern();
        $this->post('/employees/'.$intern->user->user_id.'/convert');

        $fresh = Employee::where('converted_from_id', $intern->id)->with('user')->firstOrFail();

        $this->assertSame(0, \App\Models\EmployeeSalaryStructure::where('employee_id', $fresh->id)->count());

        $this->get('/employees/'.$fresh->user->user_id)->assertOk()->assertSee('Nothing on file yet');
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT IT REFUSES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_full_time_record_cannot_be_converted(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $employee = $this->intern(['employment_type' => Employee::FULL_TIME]);

        $this->post('/employees/'.$employee->user->user_id.'/convert')
            ->assertSessionHasErrors('convert');
    }

    public function test_a_freelancer_is_not_converted(): void
    {
        // Decided 2026-09-11: interns only.
        $this->signInAsStaff(['employee', 'hr']);

        $employee = $this->intern(['employment_type' => Employee::FREELANCE]);

        $this->post('/employees/'.$employee->user->user_id.'/convert')
            ->assertSessionHasErrors('convert');
    }

    public function test_converting_twice_does_not_issue_two_accounts(): void
    {
        // A double-submitted form would otherwise make a second identifier and
        // a second account for one person.
        $this->signInAsStaff(['employee', 'hr']);

        $intern = $this->intern();
        $staffId = $intern->user->user_id;

        $this->post('/employees/'.$staffId.'/convert')->assertRedirect();

        $before = User::count();
        $this->post('/employees/'.$staffId.'/convert')->assertSessionHasErrors('convert');

        $this->assertSame($before, User::count());
    }

    public function test_the_directory_permission_does_not_carry_the_conversion(): void
    {
        // It creates an account, so it is `employees.create` — §1's rule about
        // who may make one, not a looser one because the person exists already.
        $this->signInAsStaff(['employee', 'hr']);
        $intern = $this->intern();

        $this->signInAsStaff(['employee', 'team_lead']);

        $this->post('/employees/'.$intern->user->user_id.'/convert')->assertForbidden();
    }

    public function test_it_is_audited_against_both_identifiers(): void
    {
        /*
         * Somebody reading either record has to be able to see that the other
         * one exists. An entry on one of them is half a trail.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $intern = $this->intern();
        $oldStaffId = $intern->user->user_id;

        $this->post('/employees/'.$oldStaffId.'/convert');

        $fresh = Employee::where('converted_from_id', $intern->id)->with('user')->firstOrFail();

        $entries = DB::table('audit_log')->where('action', AuditLog::EMPLOYEE_CONVERTED)->get();

        $this->assertCount(2, $entries);
        $this->assertEqualsCanonicalizing(
            [$oldStaffId, $fresh->user->user_id],
            $entries->pluck('entity_id')->all(),
        );
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * An intern six months in, with documents on file.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function intern(array $overrides = []): Employee
    {
        $this->post('/employees', $overrides + [
            'name' => 'New Intern',
            'email' => 'new.intern@example.test',
            'employment_type' => Employee::INTERN,
            'department_id' => MasterDataItem::inList(MasterDataItem::DEPARTMENTS)->value('id'),
            'designation_id' => MasterDataItem::inList(MasterDataItem::DESIGNATIONS)->value('id'),
            'joined_on' => Carbon::now()->subMonths(6)->toDateString(),
            'announce_milestones' => '1',

            // Required of every engagement since 2026-09-17, and it comes
            // across with the rest when they are converted.
            'phone' => '+91 98100 00000',
            'personal_email' => 'the.intern@personal.test',

            'current_address' => "1 Somewhere Road\nKolkata",
            'permanent_address' => "2 Elsewhere Lane\nHowrah",

            'basic' => '60000',
            'hra' => '24000',
            'allowances' => '6000',
            'pf' => '1800',
            'pt' => '200',
            'tds' => '5000',
            'stipend' => '15000',

            'id_proof_type' => IdProof::AADHAAR,
            'id_proof_number' => '123456781234',
            'pan' => 'ABCDE1234F',
            'bank_name' => 'HDFC Bank',
            'ifsc' => 'HDFC0001234',
            'account_number' => '50100234567890',
        ])->assertRedirect();

        return Employee::whereHas('user', fn ($q) => $q->where('email', 'new.intern@example.test'))
            ->with('user')
            ->firstOrFail();
    }
}
