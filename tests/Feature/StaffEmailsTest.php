<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\StaffEmails;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Personal email required, work email optional (owner, 2026-10-27).
 * See App\Support\StaffEmails.
 */
class StaffEmailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_without_a_work_email_the_personal_email_is_the_sign_in(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload(['personal_email' => 'asha@gmail.test']))->assertRedirect();

        $user = User::where('name', 'Asha Roy')->firstOrFail();

        $this->assertSame('asha@gmail.test', $user->email);
        $this->assertSame($user->id, StaffEmails::findUser('asha@gmail.test')?->id);
    }

    public function test_once_a_work_email_is_added_both_sign_in_and_mail_goes_to_work(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload(['personal_email' => 'asha@gmail.test']));
        $user = User::where('name', 'Asha Roy')->firstOrFail();

        $this->post('/employees/'.$user->user_id, $this->payload([
            'personal_email' => '',
            'work_email' => 'asha@zephryxlabs.test',
        ]))->assertRedirect();

        $user->refresh();

        $this->assertSame('asha@zephryxlabs.test', $user->email);
        $this->assertSame($user->id, StaffEmails::findUser('asha@zephryxlabs.test')?->id);
        $this->assertSame($user->id, StaffEmails::findUser('asha@gmail.test')?->id);

        // A reset asked for with the PERSONAL address goes to the WORK one.
        auth()->logout();
        $this->post('/forgot-password', ['identifier' => 'asha@gmail.test']);

        Mail::assertSent(PasswordResetMail::class, fn ($m) => $m->hasTo('asha@zephryxlabs.test'));
    }

    public function test_an_email_used_by_another_person_is_refused_either_way(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload(['personal_email' => 'asha@gmail.test']));

        // Somebody else's personal email cannot be a new person's work email…
        $this->post('/employees', $this->payload([
            'name' => 'Other Person',
            'personal_email' => 'other@gmail.test',
            'work_email' => 'asha@gmail.test',
        ]))->assertSessionHasErrors('work_email');

        // …or their personal email.
        $this->post('/employees', $this->payload([
            'name' => 'Other Person',
            'personal_email' => 'asha@gmail.test',
        ]))->assertSessionHasErrors('personal_email');
    }

    public function test_a_closed_accounts_personal_email_does_not_sign_in(): void
    {
        $user = User::factory()->create([
            'email' => 'closed@zephryxlabs.test', 'account_type' => 'staff',
            'staff_kind' => 'employee', 'status' => 'inactive',
        ]);
        $employee = Employee::create(['user_id' => $user->id, 'joined_on' => now()->subYear()]);
        EmployeeProfile::create(['employee_id' => $employee->id, 'personal_email' => 'old@gmail.test']);

        $this->assertNull(StaffEmails::findUser('old@gmail.test'));
        $this->assertFalse(StaffEmails::takenByAnother('old@gmail.test', null));
    }

    public function test_the_personal_email_is_required_when_adding(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->payload(['personal_email' => '']))
            ->assertSessionHasErrors('personal_email');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        // A complete full-time hire — none of these cases is about the
        // identity, bank or pay fields, so they are simply all present.
        return $overrides + [
            'name' => 'Asha Roy',
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
