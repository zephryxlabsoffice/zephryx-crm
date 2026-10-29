<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * v1.2.3, the owner's three reports:
 *
 *  1. A photo HR saved was never seen - the record page, the list and the top
 *     bar only ever drew initials.
 *  2. Fields on My Profile (gender, languages, emergency contact...) could not
 *     be set from Edit Employee.
 *  3. Nobody edits their own employee record. HR still keeps the CEO's: "CEO
 *     holds utmost power but he/she is an Employee".
 */
class EmployeeRecordFixesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
    }

    /* ══════════════════════════════════════════════════════════════════════
       3. YOUR OWN RECORD
       ══════════════════════════════════════════════════════════════════════ */

    public function test_hr_cannot_edit_their_own_record(): void
    {
        $hr = $this->signInAsStaff(['hr']);
        $this->employeeFor($hr);

        $this->get('/employees/'.$hr->user_id.'/edit')->assertForbidden();
        $this->post('/employees/'.$hr->user_id, $this->editPayload(['name' => 'Renamed Myself']))
            ->assertForbidden();

        $this->assertNotSame('Renamed Myself', $hr->fresh()->name);
    }

    public function test_hr_is_not_offered_the_edit_button_on_their_own_record(): void
    {
        $hr = $this->signInAsStaff(['hr']);
        $this->employeeFor($hr);

        $this->get('/employees/'.$hr->user_id)
            ->assertOk()
            ->assertDontSee('/employees/'.$hr->user_id.'/edit', false);
    }

    public function test_hr_edits_the_ceos_record(): void
    {
        $ceo = $this->person('EMP700', 'The Ceo', ['ceo']);

        $this->signInAsStaff(['hr']);

        $this->get('/employees/EMP700')->assertSee('/employees/EMP700/edit', false);
        $this->get('/employees/EMP700/edit')->assertOk();
        $this->post('/employees/EMP700', $this->editPayload(['name' => 'The Ceo Corrected']))
            ->assertRedirect();

        $this->assertSame('The Ceo Corrected', $ceo->user->fresh()->name);
    }

    public function test_hr_edits_another_hr(): void
    {
        $other = $this->person('EMP701', 'Other Hr', ['hr']);

        $this->signInAsStaff(['hr']);

        $this->post('/employees/EMP701', $this->editPayload(['name' => 'Other Hr Corrected']))
            ->assertRedirect();

        $this->assertSame('Other Hr Corrected', $other->user->fresh()->name);
    }

    /* ══════════════════════════════════════════════════════════════════════
       2. THE PERSONAL DETAILS MY PROFILE SHOWS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_hr_sets_the_personal_details_from_edit_employee(): void
    {
        $person = $this->person('EMP702', 'Some Person');

        $this->signInAsStaff(['hr']);

        $this->post('/employees/EMP702', $this->editPayload([
            'personal_present' => '1',
            'gender' => 'Female',
            'marital_status' => 'Single',
            'nationality' => 'Indian',
            'languages' => 'English, Hindi, , English',
            'skills' => 'Laravel, Figma',
            'emergency_name' => 'A Parent',
            'emergency_relationship' => 'Mother',
            'emergency_phone' => '+91 98300 00000',
        ]))->assertRedirect();

        $profile = EmployeeProfile::where('employee_id', $person->id)->firstOrFail();

        $this->assertSame('Female', $profile->gender);
        $this->assertSame('Single', $profile->marital_status);
        $this->assertSame('Indian', $profile->nationality);
        $this->assertSame(['English', 'Hindi'], $profile->languages);
        $this->assertSame(['Laravel', 'Figma'], $profile->skills);
        $this->assertSame('A Parent', $profile->emergency_name);
        $this->assertSame('Mother', $profile->emergency_relationship);
        $this->assertSame('+91 98300 00000', $profile->emergency_phone);

        // And the form shows them back.
        $this->get('/employees/EMP702/edit')
            ->assertOk()
            ->assertSee('value="Indian"', false)
            ->assertSee('English, Hindi')
            ->assertSee('A Parent');

        // Emptying a box clears it.
        $this->post('/employees/EMP702', $this->editPayload([
            'personal_present' => '1',
            'nationality' => '',
            'skills' => '',
        ]))->assertRedirect();

        $profile->refresh();
        $this->assertNull($profile->nationality);
        $this->assertSame([], $profile->skills);
    }

    public function test_a_gender_not_on_the_list_is_refused(): void
    {
        $this->person('EMP703', 'Some Person');

        $this->signInAsStaff(['hr']);

        $this->post('/employees/EMP703', $this->editPayload(['personal_present' => '1', 'gender' => 'Robot']))
            ->assertSessionHasErrors('gender');
    }

    /* ══════════════════════════════════════════════════════════════════════
       1. THE PHOTO IS SEEN
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_photo_hr_saves_shows_on_the_record_and_the_list(): void
    {
        $this->person('EMP704', 'Photo Person');

        $this->signInAsStaff(['hr']);

        $this->post('/employees/EMP704', $this->editPayload(['photo' => $this->jpeg()]))
            ->assertRedirect();

        $this->get('/employees/EMP704')->assertSee('/employees/EMP704/photo', false);
        $this->get('/employees')->assertSee('/employees/EMP704/photo', false);

        $this->get('/employees/EMP704/photo')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_no_photo_is_a_404_and_no_permission_is_a_403(): void
    {
        $this->person('EMP705', 'No Photo');

        $this->signInAsStaff(['hr']);
        $this->get('/employees/EMP705/photo')->assertNotFound();

        $this->signInAsStaff(['employee']);
        $this->get('/employees/EMP705/photo')->assertForbidden();
    }

    public function test_the_top_bar_shows_your_own_photo(): void
    {
        $user = $this->signInAsStaff(['employee']);
        $employee = $this->employeeFor($user);

        $this->get('/profile')->assertDontSee('<img src="'.route('profile.photo.show').'"', false);

        $path = 'employees/'.$employee->id.'/photo/me.jpg';
        Storage::disk('local')->put($path, 'x');
        EmployeeProfile::create(['employee_id' => $employee->id, 'photo_path' => $path]);

        $this->get('/profile')->assertSee('<img src="'.route('profile.photo.show').'"', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    protected function employeeFor(User $user): Employee
    {
        return Employee::create([
            'user_id' => $user->id,
            'joined_on' => Carbon::now()->subYear(),
        ]);
    }

    /**
     * @param  list<string>  $roles
     */
    protected function person(string $staffId, string $name, array $roles = ['employee']): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'name' => $name,
            'email' => strtolower($staffId).'@example.test',
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        return $this->employeeFor($user)->load('user');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function editPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Unchanged Name',
            'joined_on' => Carbon::now()->subYear()->toDateString(),
        ];
    }

    protected function jpeg(): UploadedFile
    {
        // The same 1×1 JPEG ProfilePhotoTest uses.
        return UploadedFile::fake()->createWithContent('photo.jpg', base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            .'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAA'
            .'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
        ));
    }
}
