<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * HR's employee form, 2026-10-27: reports-to, a profile photo and roles —
 * all optional, and roles only below the editor's own standing.
 */
class EmployeeFormAdditionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_hr_can_set_reports_to_and_give_a_team_lead_role_when_adding(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload(['name' => 'Boss', 'personal_email' => 'boss@gmail.test']));
        $boss = Employee::whereHas('user', fn ($q) => $q->where('name', 'Boss'))->firstOrFail();

        $this->post('/employees', $this->payload([
            'reports_to' => $boss->id,
            'roles' => ['team_lead'],
            'roles_present' => '1',
        ]))->assertRedirect();

        $user = User::where('name', 'Asha Roy')->firstOrFail();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $this->assertSame($boss->id, $employee->reports_to);
        $this->assertEqualsCanonicalizing(['employee', 'team_lead'], $user->roles()->pluck('role_key')->all());
    }

    public function test_hr_cannot_give_hr_or_ceo_even_by_posting_them(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->get('/employees/create')->assertOk()
            ->assertSee('value="team_lead"', false)
            ->assertDontSee('value="ceo"', false)
            ->assertDontSee('value="hr"', false);

        $this->post('/employees', $this->payload([
            'roles' => ['ceo', 'hr', 'system_admin', 'manager'],
            'roles_present' => '1',
        ]))->assertRedirect();

        $user = User::where('name', 'Asha Roy')->firstOrFail();

        $this->assertEqualsCanonicalizing(['employee', 'manager'], $user->roles()->pluck('role_key')->all());
    }

    public function test_editing_never_strips_a_role_the_editor_could_not_give(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload());
        $user = User::where('name', 'Asha Roy')->firstOrFail();
        $user->roles()->syncWithoutDetaching(\App\Models\Role::where('role_key', 'ceo')->pluck('id'));

        // HR saves the form with nothing ticked.
        $this->post('/employees/'.$user->user_id, $this->payload([
            'personal_email' => '',
            'roles_present' => '1',
        ]))->assertRedirect();

        $this->assertEqualsCanonicalizing(['employee', 'ceo'], $user->fresh()->roles()->pluck('role_key')->all());
    }

    public function test_hr_can_upload_a_profile_photo(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload([
            'photo' => UploadedFile::fake()->image('face.jpg', 400, 400),
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $employee = Employee::whereHas('user', fn ($q) => $q->where('name', 'Asha Roy'))->with('profile')->firstOrFail();

        $this->assertNotNull($employee->profile?->photo_path);
    }

    public function test_nothing_new_is_required(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload())->assertRedirect()->assertSessionHasNoErrors();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Asha Roy',
            'personal_email' => 'p'.uniqid().'@gmail.test',
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
            'announce_milestones' => '1',
            'basic' => '60000',
            'hra' => '24000',
            'allowances' => '6000',
            'pf' => '1800',
            'pt' => '200',
            'tds' => '5000',
        ];
    }
}
