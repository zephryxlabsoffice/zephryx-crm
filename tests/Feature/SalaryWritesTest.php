<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalaryRecord;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Money;
use App\Support\Rbac\Rbac;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Salary — adding a payslip, marking people paid, and handing a file over.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE MODULE WHERE A MISTAKE IS SOMEBODY'S PAY
 *
 * Four things this file exists to hold: the figure survives the round trip as
 * integer paise, marking paid is idempotent, nobody runs payroll on themselves,
 * and a stored payslip is only ever handed over after a check and an audit
 * entry.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class SalaryWritesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The payslip files go to a fake disk; nothing here writes to the real
        // storage directory.
        Storage::fake('local');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE GUARDS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_seeing_payroll_and_running_it_are_different_permissions(): void
    {
        // Somebody trusted to answer a question about the payroll is not
        // thereby somebody who may mark twelve people paid.
        $this->assertTrue((bool) Permission::where('permission_key', 'salary.view')->value('is_sensitive'));
        $this->assertTrue((bool) Permission::where('permission_key', 'salary.manage')->value('is_sensitive'));
    }

    public function test_an_employee_can_reach_neither_payroll_nor_its_writes(): void
    {
        $subject = $this->anEmployee('EMP870', 'Their Person');
        $this->aRecord($subject);

        $this->signInAsEmployee();

        $this->get('/salary')->assertForbidden();
        $this->get('/salary/EMP870/'.$this->period())->assertForbidden();
        $this->post('/salary/pay', [
            'period' => $this->period(),
            'employees' => ['EMP870'],
        ])->assertForbidden();
    }

    public function test_an_employee_reaches_their_own_pay_without_any_permission(): void
    {
        // Their own payslips are part of the Employee base (§2.2), and the
        // route takes no employee at all.
        $me = $this->signInAsEmployee();
        $this->aRecord($me, ['net_minor' => 7500000]);

        $this->get('/salary/mine')->assertOk();
        $this->get('/salary/payslip/'.$this->period())->assertOk();
    }

    /* ══════════════════════════════════════════════════════════════════════
       ADDING A PAYSLIP
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_net_survives_as_integer_paise(): void
    {
        /*
         * Typed in rupees, stored in paise, and never a float in between.
         * 0.1 + 0.2 is not 0.3 in binary floating point and somebody's pay is
         * the least forgivable place for that.
         */
        $subject = $this->anEmployee('EMP871', 'Their Person');
        $this->signInAsPayroll();

        $this->post('/salary/EMP871/'.$this->period().'/payslip', [
            'net' => '75000.50',
            'payslip' => UploadedFile::fake()->create('payslip.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $record = SalaryRecord::where('employee_id', $subject->id)->firstOrFail();

        $this->assertSame(7500050, $record->net_minor);
        // Grouped the Indian way when it is read, ungrouped when it goes back
        // into the form — a field prefilled with "75,000.50" would be refused
        // by the validator that wrote it.
        $this->assertSame('75,000.50', $record->net()->decimal());
        $this->assertSame('75000.50', $record->net()->plain());
    }

    public function test_a_prefilled_amount_can_be_saved_again_untouched(): void
    {
        // The round trip, end to end: what the form renders is what the
        // validator accepts.
        $subject = $this->anEmployee('EMP883', 'Their Person');
        $record = $this->aRecord($subject, ['net_minor' => 7500050]);

        $this->signInAsPayroll();

        $this->post('/salary/EMP883/'.$this->period().'/payslip', [
            'net' => $record->net()->plain(),
            'payslip' => UploadedFile::fake()->create('payslip.pdf', 40, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(7500050, $record->fresh()->net_minor);
    }

    public function test_the_file_is_stored_off_the_webroot_under_a_name_we_chose(): void
    {
        /*
         * An uploaded name reaching the filesystem is how "../../.env" and
         * "payslip.pdf.php" become a problem. The original is kept in the
         * database for display and never used as a path.
         */
        $this->anEmployee('EMP872', 'Their Person');
        $this->signInAsPayroll();

        $this->post('/salary/EMP872/'.$this->period().'/payslip', [
            'net' => '50000',
            'payslip' => UploadedFile::fake()->create('September payslip.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $record = SalaryRecord::query()->latest('id')->firstOrFail();

        $this->assertStringStartsWith('payslips/'.$this->period().'/', $record->payslip_path);
        $this->assertStringEndsWith('.pdf', $record->payslip_path);
        $this->assertStringNotContainsString('September payslip', $record->payslip_path);
        $this->assertSame('September payslip.pdf', $record->payslip_name);

        Storage::disk('local')->assertExists($record->payslip_path);
    }

    public function test_a_file_that_is_not_an_allowed_type_is_refused(): void
    {
        $this->anEmployee('EMP873', 'Their Person');
        $this->signInAsPayroll();

        $this->post('/salary/EMP873/'.$this->period().'/payslip', [
            'net' => '50000',
            'payslip' => UploadedFile::fake()->create('payslip.php', 4, 'application/x-php'),
        ])->assertSessionHasErrors('payslip');

        $this->assertSame(0, SalaryRecord::count());
    }

    public function test_nobody_adds_a_payslip_to_their_own_record(): void
    {
        // The same rule as nobody approving their own leave, and this is the
        // one where it means setting your own pay.
        $me = $this->signInAsPayroll();

        $this->post('/salary/'.$me->user->user_id.'/'.$this->period().'/payslip', [
            'net' => '999999',
            'payslip' => UploadedFile::fake()->create('payslip.pdf', 40, 'application/pdf'),
        ])->assertForbidden();

        $this->assertSame(0, SalaryRecord::count());
    }

    public function test_a_paid_month_cannot_have_its_payslip_replaced(): void
    {
        // The document and the transfer would disagree, and the transfer is the
        // one that happened.
        $subject = $this->anEmployee('EMP874', 'Their Person');
        $this->aRecord($subject, ['net_minor' => 5000000, 'paid_on' => Carbon::today()->subDay()]);

        $this->signInAsPayroll();

        $this->post('/salary/EMP874/'.$this->period().'/payslip', [
            'net' => '60000',
            'payslip' => UploadedFile::fake()->create('payslip.pdf', 40, 'application/pdf'),
        ])->assertSessionHasErrors('payslip');

        $this->assertSame(5000000, SalaryRecord::where('employee_id', $subject->id)->value('net_minor'));
    }

    public function test_replacing_a_payslip_records_what_the_figure_was(): void
    {
        $subject = $this->anEmployee('EMP875', 'Their Person');
        $this->aRecord($subject, ['net_minor' => 5000000, 'payslip_name' => 'old.pdf']);

        $this->signInAsPayroll();

        $this->post('/salary/EMP875/'.$this->period().'/payslip', [
            'net' => '60000',
            'payslip' => UploadedFile::fake()->create('new.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $entry = DB::table('audit_log')->where('action', AuditLog::SALARY_PAYSLIP_ADDED)->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('50,000', (string) $entry->before_json);
        $this->assertStringContainsString('60,000', (string) $entry->after_json);
    }

    /* ══════════════════════════════════════════════════════════════════════
       MARKING PAID
       ══════════════════════════════════════════════════════════════════════ */

    public function test_marking_paid_is_idempotent(): void
    {
        /*
         * The date a record carries is when the money actually moved. Moving it
         * because somebody pressed the button twice would make the payroll
         * trail describe the button rather than the transfer.
         */
        $subject = $this->anEmployee('EMP876', 'Their Person');
        $record = $this->aRecord($subject, ['net_minor' => 5000000]);

        $this->signInAsPayroll();

        $this->post('/salary/pay', [
            'period' => $this->period(),
            'employees' => ['EMP876'],
            'paid_on' => Carbon::today()->subDays(3)->toDateString(),
        ])->assertRedirect();

        $first = $record->fresh()->paid_on;

        $this->post('/salary/pay', [
            'period' => $this->period(),
            'employees' => ['EMP876'],
        ])->assertRedirect();

        $this->assertEquals($first, $record->fresh()->paid_on);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::SALARY_PAID)->count());
    }

    public function test_a_record_with_no_payslip_is_never_marked_paid(): void
    {
        // Nothing to check the amount against, and nothing to give them if they
        // ask what they were paid for.
        $subject = $this->anEmployee('EMP877', 'Their Person');
        $record = $this->aRecord($subject, ['net_minor' => null, 'payslip_name' => null]);

        $this->signInAsPayroll();

        $this->post('/salary/pay', [
            'period' => $this->period(),
            'employees' => ['EMP877'],
        ])->assertRedirect();

        $this->assertNull($record->fresh()->paid_on);
    }

    public function test_the_audit_entry_is_per_person_not_per_batch(): void
    {
        // "When was Amit paid for September" has to be answerable without
        // reading twelve names out of one entry's payload.
        $one = $this->anEmployee('EMP878', 'One Person');
        $two = $this->anEmployee('EMP879', 'Two Person');

        $this->aRecord($one, ['net_minor' => 4000000]);
        $this->aRecord($two, ['net_minor' => 4500000]);

        $this->signInAsPayroll();

        $this->post('/salary/pay', [
            'period' => $this->period(),
            'employees' => ['EMP878', 'EMP879'],
        ])->assertRedirect();

        $entries = DB::table('audit_log')->where('action', AuditLog::SALARY_PAID)->get();

        $this->assertCount(2, $entries);
        $this->assertEqualsCanonicalizing(
            [$this->period().'-EMP878', $this->period().'-EMP879'],
            $entries->pluck('entity_id')->all(),
        );
    }

    public function test_nobody_marks_themselves_paid_even_inside_a_batch(): void
    {
        $me = $this->signInAsPayroll();
        $other = $this->anEmployee('EMP880', 'Their Person');

        $mine = $this->aRecord($me, ['net_minor' => 9900000]);
        $theirs = $this->aRecord($other, ['net_minor' => 4000000]);

        $this->post('/salary/pay', [
            'period' => $this->period(),
            'employees' => [$me->user->user_id, 'EMP880'],
        ])->assertRedirect();

        $this->assertNull($mine->fresh()->paid_on, 'payroll marked their own record paid');
        $this->assertNotNull($theirs->fresh()->paid_on);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE DOWNLOAD — THE ONE WAY A STORED FILE LEAVES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_somebody_may_download_their_own_payslip(): void
    {
        $me = $this->signInAsEmployee();
        $this->aRecordWithFile($me);

        $this->get('/salary/'.$me->user->user_id.'/'.$this->period().'/payslip/download')
            ->assertOk()
            ->assertDownload();
    }

    public function test_a_colleague_cannot_download_somebody_elses(): void
    {
        $subject = $this->anEmployee('EMP881', 'Their Person');
        $this->aRecordWithFile($subject);

        $this->signInAsEmployee();

        $this->get('/salary/EMP881/'.$this->period().'/payslip/download')->assertForbidden();
    }

    public function test_payroll_may_download_it_and_the_download_is_recorded(): void
    {
        /*
         * A payslip is the one document here whose having-been-read is itself
         * a fact somebody may need to establish.
         */
        $subject = $this->anEmployee('EMP882', 'Their Person');
        $this->aRecordWithFile($subject);

        $this->signInAsPayroll();

        $this->get('/salary/EMP882/'.$this->period().'/payslip/download')->assertOk();

        $entry = DB::table('audit_log')->where('action', AuditLog::SALARY_PAYSLIP_DOWNLOADED)->first();

        $this->assertNotNull($entry);
        $this->assertSame($this->period().'-EMP882', $entry->entity_id);
    }

    public function test_a_record_with_no_stored_file_is_a_404_rather_than_an_error(): void
    {
        // A month can say a payslip was added and have no file behind it — an
        // imported month, or one recorded before this application held them.
        $me = $this->signInAsEmployee();
        $this->aRecord($me, ['net_minor' => 5000000, 'payslip_name' => 'legacy.pdf']);

        $this->get('/salary/'.$me->user->user_id.'/'.$this->period().'/payslip/download')
            ->assertNotFound();
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    protected function period(): string
    {
        return Carbon::today()->format('Y-m');
    }

    /**
     * @param  list<string>  $roles
     */
    protected function signInAsEmployee(array $roles = ['employee'], string $staffId = 'EMP860'): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        app(Rbac::class)->forget($user);
        $this->actingAs($user);

        return Employee::with('user')->create([
            'user_id' => $user->id,
            'joined_on' => Carbon::now()->subYear(),
        ])->fresh('user');
    }

    protected function signInAsPayroll(): Employee
    {
        return $this->signInAsEmployee(['employee', 'hr'], 'EMP861');
    }

    protected function anEmployee(string $staffId, string $name): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'name' => $name,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function aRecord(Employee $employee, array $attributes = []): SalaryRecord
    {
        return SalaryRecord::create($attributes + [
            'employee_id' => $employee->id,
            'period' => $this->period(),
            'net_minor' => 5000000,
            'currency' => Money::DEFAULT_CURRENCY,
            'payslip_name' => 'payslip.pdf',
            'payslip_added_at' => Carbon::now(),
        ]);
    }

    protected function aRecordWithFile(Employee $employee): SalaryRecord
    {
        Storage::disk('local')->put('payslips/'.$this->period().'/stored.pdf', 'not a real pdf');

        return $this->aRecord($employee, [
            'payslip_path' => 'payslips/'.$this->period().'/stored.pdf',
            'payslip_bytes' => 14,
        ]);
    }
}
