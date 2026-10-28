<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalaryStructure;
use App\Support\Audit\AuditLog;
use App\Support\Updates;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 2026-10-27: Admin → Updates (database changes without a Terminal) and the
 * typed annual CTC that needed the first one.
 */
class UpdatesAndCtcTest extends TestCase
{
    protected string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // The update takes a backup; keep it out of the real storage folder.
        $this->storage = sys_get_temp_dir().'/zephryx-updates-test-'.uniqid();
        File::ensureDirectoryExists($this->storage.'/app');
        $this->app->useStoragePath($this->storage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    public function test_up_to_date_says_so(): void
    {
        $this->signInAsAdmin();

        $this->assertSame([], Updates::pending());
        $this->get('/admin/updates')->assertOk()->assertSee('Up to date');
    }

    public function test_a_pending_change_is_listed_backed_up_and_applied(): void
    {
        $this->signInAsAdmin();

        // Six steps: the CTC column, and the five 2026-10-28 updates after it.
        Artisan::call('migrate:rollback', ['--step' => 6, '--force' => true]);
        $this->assertFalse(Schema::hasColumn('employee_salary_structures', 'ctc_minor'));

        $this->assertCount(6, Updates::pending());
        $this->get('/admin/dashboard')->assertOk()->assertSee('An update is waiting to be applied');
        $this->get('/admin/updates')->assertOk()->assertSee('Add ctc to employee salary structures');

        $this->post('/admin/updates')->assertRedirect('/admin/updates');

        $this->assertTrue(Schema::hasColumn('employee_salary_structures', 'ctc_minor'));
        $this->assertSame([], Updates::pending());
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::UPDATE_APPLIED)->count());
        $this->assertNotEmpty(glob(storage_path('app/backups').'/zephryx-crm-db-*.sql.gz'));
    }

    public function test_hr_types_a_ctc_and_it_is_shown_not_calculated(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', [
            'name' => 'Asha Roy',
            'personal_email' => 'asha@gmail.test',
            'employment_type' => Employee::FULL_TIME,
            'joined_on' => now()->subMonth()->toDateString(),
            'phone' => '9800000000',
            'current_address' => "1 Somewhere Road\nKolkata",
            'permanent_address' => "2 Elsewhere Lane\nHowrah",
            'id_proof_type' => \App\Support\IdProof::AADHAAR,
            'id_proof_number' => '123456781234',
            'pan' => 'ABCDE1234F',
            'bank_name' => 'HDFC Bank',
            'ifsc' => 'HDFC0001234',
            'account_number' => '50100234567890',
            'basic' => '60000', 'hra' => '24000', 'allowances' => '6000',
            'pf' => '1800', 'pt' => '200', 'tds' => '5000',
            'ctc' => '1260000',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $employee = Employee::with('user')->whereHas('user', fn ($q) => $q->where('name', 'Asha Roy'))->firstOrFail();

        // Stored as typed, in paise - never the sum of the components.
        $this->assertSame(126000000, (int) EmployeeSalaryStructure::where('employee_id', $employee->id)->value('ctc_minor'));

        $this->get('/employees/'.$employee->user->user_id)->assertOk()->assertSee('CTC per year');
    }

    public function test_staff_cannot_reach_updates(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->get('/admin/updates')->assertNotFound();
        $this->post('/admin/updates')->assertNotFound();
    }
}
