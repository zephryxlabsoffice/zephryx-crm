<?php

namespace Tests\Feature;

use App\Http\Controllers\Verify\AdminController;
use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Realm;
use App\Support\Verify\VerifySignature;
use chillerlan\QRCode\QRCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * verify.zephryxlabs.in (2026-10-29): ID card verification, served by the CRM
 * on its own host.
 */
class VerifySiteTest extends TestCase
{
    protected const HOST = 'http://verify.zephryxlabs.in';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE QR AND THE PAGE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_card_qr_shows_somebody_who_works_here(): void
    {
        $this->person('ZEPH261001', 'Asha Rao', 'Video Editor');

        $this->get(self::HOST.'/verify/ZEPH261001?signature='.VerifySignature::forCard('ZEPH261001'))
            ->assertOk()
            ->assertSee('Currently working at')
            ->assertSee('Asha Rao')
            ->assertSee('Video Editor')
            ->assertSee('Full-time');

        $this->assertSame(1, DB::table('verify_scans')->where('source', 'qr')->count());
    }

    public function test_the_card_link_is_on_the_verify_domain_over_https(): void
    {
        $this->assertSame(
            'https://verify.zephryxlabs.in/verify/ZEPH261001?signature='.VerifySignature::forCard('ZEPH261001'),
            AdminController::cardUrl('ZEPH261001'),
        );
    }

    public function test_no_signature_or_an_altered_one_is_not_genuine(): void
    {
        $this->person('ZEPH261001', 'Asha Rao');
        $this->person('ZEPH261002', 'Someone Else');

        $this->get(self::HOST.'/verify/ZEPH261001')->assertStatus(403)->assertSee('not genuine');

        // A real signature, moved onto another ID.
        $this->get(self::HOST.'/verify/ZEPH261002?signature='.VerifySignature::forCard('ZEPH261001'))
            ->assertStatus(403)->assertDontSee('Someone Else');

        $this->assertSame(0, DB::table('verify_scans')->count());
    }

    public function test_somebody_who_left_shows_their_name_and_leaving_date_only(): void
    {
        $employee = $this->person('ZEPH261003', 'Ravi Kumar', 'Designer');
        $employee->user->update(['status' => 'inactive']);
        $employee->update(['left_on' => '2026-09-30']);

        $this->get(self::HOST.'/verify/ZEPH261003?signature='.VerifySignature::forCard('ZEPH261003'))
            ->assertOk()
            ->assertSee('Not currently with')
            ->assertSee('Ravi Kumar')
            ->assertSee('30 September 2026')
            ->assertDontSee('Designer');

        $this->get(self::HOST.'/verify/ZEPH261003/photo?signature='.VerifySignature::forCard('ZEPH261003'))->assertNotFound();
    }

    public function test_an_interns_earlier_id_points_to_their_current_one(): void
    {
        $intern = $this->person('ZEPH262001', 'Neha Das');
        $intern->user->update(['status' => 'inactive']);
        $this->person('ZEPH261004', 'Neha Das', 'Developer', $intern->id);

        $this->get(self::HOST.'/verify/ZEPH262001?signature='.VerifySignature::forCard('ZEPH262001'))
            ->assertOk()
            ->assertSee('Currently working at')
            ->assertSee("ZEPH262001 was Neha Das's internship ID", false)
            ->assertSee('ZEPH261004');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE SEARCH BOX
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_search_hands_out_a_short_lived_link(): void
    {
        $this->person('ZEPH261001', 'Asha Rao');

        $this->get(self::HOST.'/')->assertOk()->assertSee('Verify a');

        $location = $this->post(self::HOST.'/search', ['employee_id' => 'zeph261001'])
            ->assertRedirect()->headers->get('Location');

        $this->assertStringContainsString('/verify/ZEPH261001?expires=', $location);
        $this->get($location)->assertOk()->assertSee('Asha Rao');
        $this->assertSame(1, DB::table('verify_scans')->where('source', 'search')->count());

        // Fifteen minutes on, it has expired.
        Carbon::setTestNow(now()->addMinutes(16));
        $this->get($location)->assertStatus(403);
        Carbon::setTestNow();
    }

    public function test_an_unknown_id_or_a_non_employee_is_not_found(): void
    {
        $client = User::factory()->create(['user_id' => 'ZEPH5001', 'account_type' => Realm::CLIENT]);

        foreach (['ZEPH269999', $client->user_id] as $id) {
            $this->from(self::HOST.'/')->post(self::HOST.'/search', ['employee_id' => $id])
                ->assertSessionHasErrors('employee_id');
        }
    }

    public function test_the_search_box_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(self::HOST.'/search', ['employee_id' => 'ZEPH26000'.$i]);
        }

        $this->post(self::HOST.'/search', ['employee_id' => 'ZEPH269999'])->assertStatus(429);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE TWO SITES STAY APART
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_crm_is_not_reachable_on_the_verify_host(): void
    {
        foreach (['/dashboard', '/admin/accounts', '/admin/dashboard', '/client/dashboard', '/employees'] as $path) {
            $this->get(self::HOST.$path)->assertNotFound();
        }

        $this->get(self::HOST.'/login')->assertOk();
    }

    public function test_the_verify_pages_do_not_exist_on_the_crm_host(): void
    {
        $this->get('/verify/ZEPH261001?signature='.VerifySignature::forCard('ZEPH261001'))->assertNotFound();
    }

    public function test_the_verify_host_has_its_own_session_cookie(): void
    {
        config(['session.domain' => 'crm.zephryxlabs.in']);

        $response = $this->get(self::HOST.'/');

        $cookie = collect($response->headers->getCookies())->first(fn ($c) => str_ends_with($c->getName(), '_verify'));
        $this->assertNotNull($cookie);
        $this->assertNull($cookie->getDomain());
    }

    /* ══════════════════════════════════════════════════════════════════════
       /admin
       ══════════════════════════════════════════════════════════════════════ */

    public function test_admin_needs_the_crm_admin_account(): void
    {
        $this->get(self::HOST.'/admin')->assertRedirect(self::HOST.'/login');

        $this->signInAsStaff(['employee', 'system_admin']);
        $this->get(self::HOST.'/admin')->assertNotFound();
    }

    public function test_the_dashboard_counts_people_and_scans_and_gives_a_qr_png(): void
    {
        $this->person('ZEPH261001', 'Asha Rao');
        $this->person('ZEPH262002', 'Intern Person', null, null, Employee::INTERN);
        $this->get(self::HOST.'/verify/ZEPH261001?signature='.VerifySignature::forCard('ZEPH261001'));

        $this->signInAsAdmin();

        $this->get(self::HOST.'/admin')->assertOk()
            ->assertSee('Working now')
            ->assertSee('1 full-time · 1 interns · 0 freelancers')
            ->assertSee('Asha Rao')
            ->assertSee('Download QR');

        $response = $this->get(self::HOST.'/admin/qr/ZEPH261001')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
        $this->assertStringContainsString('ZEPH261001-id-card-qr.png', $response->headers->get('Content-Disposition'));
        // And it decodes to the card's address.
        $this->assertSame(AdminController::cardUrl('ZEPH261001'), (string) (new QRCode)->readFromBlob($response->getContent()));
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::VERIFY_QR_DOWNLOADED)->count());
    }

    public function test_enquiry_buttons_and_the_registration_pdf_come_from_the_dashboard(): void
    {
        $this->person('ZEPH261001', 'Asha Rao');
        $this->signInAsAdmin();

        $this->post(self::HOST.'/admin/settings', ['phone' => '+91 98765 43210', 'email' => 'hr@zephryxlabs.in'])
            ->assertRedirect();
        $this->post(self::HOST.'/admin/registration', [
            'registration' => UploadedFile::fake()->create('reg.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->get(self::HOST.'/verify/ZEPH261001?signature='.VerifySignature::forCard('ZEPH261001'))
            ->assertSee('tel:+919876543210', false)
            ->assertSee('mailto:hr@zephryxlabs.in', false)
            ->assertSee('Company registration (PDF)');

        $this->get(self::HOST.'/company-registration')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE LEAVING DATE, FROM HR
       ══════════════════════════════════════════════════════════════════════ */

    public function test_closing_a_record_stores_the_last_working_day_and_reopening_clears_it(): void
    {
        $employee = $this->person('EMP850', 'Leaving Person');
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees/EMP850/status', ['status' => 'inactive', 'left_on' => '2026-10-20'])->assertRedirect();
        $this->assertSame('2026-10-20', $employee->fresh()->left_on->toDateString());

        $this->post('/employees/EMP850/status', ['status' => 'active'])->assertRedirect();
        $this->assertNull($employee->fresh()->left_on);
    }

    protected function person(string $id, string $name, ?string $designation = null, ?int $convertedFrom = null, string $type = Employee::FULL_TIME): Employee
    {
        $user = User::factory()->create([
            'user_id' => $id,
            'name' => $name,
            'account_type' => Realm::STAFF,
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);
        $user->roles()->sync(Role::where('role_key', 'employee')->pluck('id'));

        $designationId = $designation === null ? null
            : MasterDataItem::create(['list' => MasterDataItem::DESIGNATIONS, 'name' => $designation, 'code' => strtoupper(substr($designation, 0, 3)).$id])->id;

        return Employee::create([
            'user_id' => $user->id,
            'joined_on' => Carbon::now()->subYear(),
            'designation_id' => $designationId,
            'converted_from_id' => $convertedFrom,
            'employment_type' => $type,
        ])->fresh(['user']);
    }
}
