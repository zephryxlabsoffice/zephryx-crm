<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Admin\MasterDataDirectory;
use App\Support\Audit\AuditLog;
use App\Support\IdProof;
use App\Support\Rbac\DesignationAccess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Designations placed under departments, and merging duplicates (owner, 2026-11-02).
 * Access is untouched by any of this.
 */
class DepartmentsAndDesignationsTest extends TestCase
{
    protected function row(string $list, string $name, ?int $departmentId = null, bool $active = true): MasterDataItem
    {
        return MasterDataItem::create([
            'list' => $list,
            'name' => $name,
            'code' => strtoupper(substr(md5($name), 0, 8)),
            'department_id' => $departmentId,
            'is_active' => $active,
        ]);
    }

    protected function employeeOn(?MasterDataItem $department, ?MasterDataItem $designation, string $name = 'Asha Roy'): Employee
    {
        return Employee::create([
            'user_id' => User::factory()->create(['name' => $name, 'account_type' => 'staff'])->id,
            'employment_type' => Employee::FULL_TIME,
            'department_id' => $department?->id,
            'designation_id' => $designation?->id,
            'joined_on' => now()->subYear()->toDateString(),
        ]);
    }

    public function test_an_empty_department_takes_the_designations(): void
    {
        $it = $this->row(MasterDataItem::DEPARTMENTS, 'IT');
        $job = $this->row(MasterDataItem::DESIGNATIONS, 'Technical Associate', $it->id);

        $this->assertSame($it->id, MasterDataItem::departmentForJob($job->id, null));
    }

    public function test_a_different_department_is_refused_with_the_reason(): void
    {
        $it = $this->row(MasterDataItem::DEPARTMENTS, 'IT');
        $content = $this->row(MasterDataItem::DEPARTMENTS, 'Content');
        $job = $this->row(MasterDataItem::DESIGNATIONS, 'Technical Associate', $it->id);

        try {
            MasterDataItem::departmentForJob($job->id, $content->id);
            $this->fail('A department the designation does not belong to was accepted.');
        } catch (ValidationException $refused) {
            $this->assertStringContainsString('Technical Associate belongs to IT', $refused->errors()['department_id'][0]);
        }
    }

    public function test_an_old_mismatch_does_not_block_editing_the_rest_of_the_record(): void
    {
        $it = $this->row(MasterDataItem::DEPARTMENTS, 'IT');
        $content = $this->row(MasterDataItem::DEPARTMENTS, 'Content');
        $job = $this->row(MasterDataItem::DESIGNATIONS, 'Technical Associate', $it->id);

        $employee = $this->employeeOn($content, $job);

        $this->assertSame($content->id, MasterDataItem::departmentForJob($job->id, $content->id, $employee));
    }

    public function test_an_unplaced_designation_or_a_retired_department_asks_nothing(): void
    {
        $content = $this->row(MasterDataItem::DEPARTMENTS, 'Content');
        $retired = $this->row(MasterDataItem::DEPARTMENTS, 'Old', active: false);

        $unplaced = $this->row(MasterDataItem::DESIGNATIONS, 'Editor');
        $stranded = $this->row(MasterDataItem::DESIGNATIONS, 'Writer', $retired->id);

        $this->assertSame($content->id, MasterDataItem::departmentForJob($unplaced->id, $content->id));
        $this->assertSame($content->id, MasterDataItem::departmentForJob($stranded->id, $content->id));
        $this->assertNull(MasterDataItem::departmentForJob($stranded->id, null));
    }

    public function test_the_dropdown_groups_by_active_department_and_keeps_the_rest_last(): void
    {
        MasterDataItem::whereIn('list', [MasterDataItem::DEPARTMENTS, MasterDataItem::DESIGNATIONS])->update(['is_active' => false]);

        $it = $this->row(MasterDataItem::DEPARTMENTS, 'IT');
        $retired = $this->row(MasterDataItem::DEPARTMENTS, 'Old', active: false);

        $this->row(MasterDataItem::DESIGNATIONS, 'Technical Associate', $it->id);
        $this->row(MasterDataItem::DESIGNATIONS, 'Writer', $retired->id);
        $this->row(MasterDataItem::DESIGNATIONS, 'Editor');

        $groups = collect(MasterDataDirectory::designationGroups())
            ->mapWithKeys(fn (array $group) => [$group['label'] => $group['items']->pluck('name')->all()]);

        $this->assertSame([
            'IT' => ['Technical Associate'],
            'Not placed yet' => ['Editor', 'Writer'],
        ], $groups->all());
    }

    public function test_the_employee_form_refuses_a_mismatch_and_fills_an_empty_department(): void
    {
        $it = $this->row(MasterDataItem::DEPARTMENTS, 'IT');
        $content = $this->row(MasterDataItem::DEPARTMENTS, 'Content');
        $job = $this->row(MasterDataItem::DESIGNATIONS, 'Technical Associate', $it->id);

        $this->signInAsStaff(['employee', 'hr']);

        $person = [
            'name' => 'Asha Roy',
            'personal_email' => 'asha@gmail.test',
            'employment_type' => Employee::FULL_TIME,
            'joined_on' => now()->subMonth()->toDateString(),
            'phone' => '9800000000',
            'current_address' => "1 Somewhere Road\nKolkata",
            'permanent_address' => "2 Elsewhere Lane\nHowrah",
            'id_proof_type' => IdProof::AADHAAR,
            'id_proof_number' => '123456781234',
            'pan' => 'ABCDE1234F',
            'bank_name' => 'HDFC Bank',
            'ifsc' => 'HDFC0001234',
            'account_number' => '50100234567890',
            'basic' => '60000', 'hra' => '24000', 'allowances' => '6000', 'pf' => '1800', 'pt' => '200', 'tds' => '5000',
            'designation_id' => $job->id,
        ];

        $this->post('/employees', $person + ['department_id' => $content->id])
            ->assertSessionHasErrors('department_id');

        $this->post('/employees', $person)->assertSessionHasNoErrors();

        $this->assertSame($it->id, Employee::where('designation_id', $job->id)->value('department_id'));
    }

    public function test_placing_a_designation_moves_no_employee_and_says_who_sits_elsewhere(): void
    {
        $this->signInAsAdmin();

        $it = $this->row(MasterDataItem::DEPARTMENTS, 'IT');
        $content = $this->row(MasterDataItem::DEPARTMENTS, 'Content');
        $job = $this->row(MasterDataItem::DESIGNATIONS, 'Technical Associate');
        $employee = $this->employeeOn($content, $job);

        $this->post('/admin/master-data/designations/place', ['item' => $job->id, 'department_id' => $it->id])
            ->assertRedirect('/admin/master-data/designations')
            ->assertSessionHas('status', fn (string $status) => str_contains($status, '1 person holding it sits in another department'));

        $this->assertSame($it->id, $job->fresh()->department_id);
        $this->assertSame($content->id, $employee->fresh()->department_id);
    }

    public function test_only_a_department_can_be_chosen_when_placing(): void
    {
        $this->signInAsAdmin();

        $job = $this->row(MasterDataItem::DESIGNATIONS, 'Technical Associate');
        $other = $this->row(MasterDataItem::DESIGNATIONS, 'Editor');

        $this->post('/admin/master-data/designations/place', ['item' => $job->id, 'department_id' => $other->id])
            ->assertSessionHasErrors('department_id');

        $this->assertNull($job->fresh()->department_id);
    }

    public function test_adding_a_designation_can_place_it(): void
    {
        $this->signInAsAdmin();

        $it = $this->row(MasterDataItem::DEPARTMENTS, 'IT');

        $this->post('/admin/master-data/designations', ['name' => 'SDE', 'code' => 'SDE', 'department_id' => $it->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($it->id, MasterDataItem::where('name', 'SDE')->value('department_id'));
    }

    public function test_the_merge_page_names_who_moves_before_anything_does(): void
    {
        $this->signInAsAdmin();

        $old = $this->row(MasterDataItem::DEPARTMENTS, 'Old');
        $this->row(MasterDataItem::DEPARTMENTS, 'New');
        $employee = $this->employeeOn($old, null);

        $this->get('/admin/master-data/departments/merge?item='.$old->id)
            ->assertOk()
            ->assertSee('Asha Roy')
            ->assertSee('New');

        $this->assertSame($old->id, $employee->fresh()->department_id);
    }

    public function test_merging_a_department_moves_everything_pointing_at_it_and_deletes_it(): void
    {
        $this->signInAsAdmin();

        $old = $this->row(MasterDataItem::DEPARTMENTS, 'Old');
        $new = $this->row(MasterDataItem::DEPARTMENTS, 'New');
        $job = $this->row(MasterDataItem::DESIGNATIONS, 'Editor', $old->id);
        $employee = $this->employeeOn($old, $job);

        $announcementId = DB::table('announcements')->insertGetId([
            'reference' => 'ANN-TEST-1', 'title' => 'For Old', 'body' => 'Hello', 'category' => 'general',
            'audience' => 'department', 'audience_department_id' => $old->id,
            'author_id' => $employee->id, 'starts_on' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ticket = Ticket::create([
            'reference' => 'TKT-2026-960', 'type' => 'internal', 'subject' => 'For Old',
            'description' => 'Hello', 'status' => 'unassigned', 'department' => 'Old',
        ]);

        $this->post('/admin/master-data/departments/merge', ['item' => $old->id, 'into' => $new->id])
            ->assertRedirect('/admin/master-data/departments')
            ->assertSessionHasNoErrors();

        $this->assertSame($new->id, $employee->fresh()->department_id);
        $this->assertSame($new->id, $job->fresh()->department_id);
        $this->assertSame($new->id, Announcement::find($announcementId)->audience_department_id);
        $this->assertSame('New', $ticket->fresh()->department);
        $this->assertModelMissing($old);

        $entry = DB::table('audit_log')->where('action', AuditLog::MASTER_DATA_CHANGED)->latest('id')->first();

        $this->assertStringContainsString('merged into New and deleted; moved: Asha Roy', $entry->after_json);
    }

    public function test_merging_into_a_placed_designation_takes_its_department(): void
    {
        $this->signInAsAdmin();

        $it = $this->row(MasterDataItem::DEPARTMENTS, 'IT');
        $content = $this->row(MasterDataItem::DEPARTMENTS, 'Content');
        $old = $this->row(MasterDataItem::DESIGNATIONS, 'Tech Assoc');
        $new = $this->row(MasterDataItem::DESIGNATIONS, 'Technical Associate', $it->id);
        $employee = $this->employeeOn($content, $old);

        $this->post('/admin/master-data/designations/merge', ['item' => $old->id, 'into' => $new->id])
            ->assertSessionHasNoErrors();

        $employee->refresh();

        $this->assertSame($new->id, $employee->designation_id);
        $this->assertSame($it->id, $employee->department_id);
    }

    public function test_each_designation_option_names_its_active_department_for_the_form_to_narrow_by(): void
    {
        $it = $this->row(MasterDataItem::DEPARTMENTS, 'IT');
        $retired = $this->row(MasterDataItem::DEPARTMENTS, 'Old', active: false);
        $placed = $this->row(MasterDataItem::DESIGNATIONS, 'Technical Associate', $it->id);
        $unplaced = $this->row(MasterDataItem::DESIGNATIONS, 'Editor');
        $stranded = $this->row(MasterDataItem::DESIGNATIONS, 'Writer', $retired->id);

        $options = view('partials.designation-options', [
            'designationGroups' => MasterDataDirectory::designationGroups(),
            'selected' => null,
        ])->render();

        $this->assertStringContainsString('value="'.$placed->id.'" data-department="'.$it->id.'"', $options);
        $this->assertStringContainsString('value="'.$unplaced->id.'" data-department=""', $options);
        $this->assertStringContainsString('value="'.$stranded->id.'" data-department=""', $options);
    }

    public function test_the_employee_form_ties_its_designation_list_to_its_department(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->get('/employees/create')
            ->assertOk()
            ->assertSee('data-filtered-by="emp-department"', false);
    }

    public function test_merging_a_designation_deletes_it_and_the_audit_keeps_what_it_carried(): void
    {
        $this->signInAsAdmin();

        $old = $this->row(MasterDataItem::DESIGNATIONS, 'Tech Assoc');
        $new = $this->row(MasterDataItem::DESIGNATIONS, 'Technical Associate');
        $employee = $this->employeeOn(null, $old);

        DB::table('designation_roles')->insert([
            'designation_id' => $old->id,
            'role_id' => Role::where('role_key', 'hr')->value('id'),
        ]);
        DB::table('designation_permissions')->insert([
            'designation_id' => $old->id,
            'permission_id' => Permission::where('permission_key', 'tickets.triage')->value('id'),
            'effect' => DesignationAccess::NEVER,
        ]);

        $this->post('/admin/master-data/designations/merge', ['item' => $old->id, 'into' => $new->id])
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($old);
        $this->assertSame($new->id, $employee->fresh()->designation_id);
        $this->assertDatabaseMissing('designation_roles', ['designation_id' => $old->id]);
        $this->assertDatabaseMissing('designation_permissions', ['designation_id' => $old->id]);

        $entry = DB::table('audit_log')->where('action', AuditLog::MASTER_DATA_CHANGED)->latest('id')->first();

        $this->assertStringContainsString('it carried roles: hr; never tickets.triage', $entry->after_json);
    }

    public function test_a_retired_row_left_by_an_earlier_merge_can_be_merged_away(): void
    {
        $this->signInAsAdmin();

        $retired = $this->row(MasterDataItem::DEPARTMENTS, 'Old', active: false);
        $new = $this->row(MasterDataItem::DEPARTMENTS, 'New');

        $this->post('/admin/master-data/departments/merge', ['item' => $retired->id, 'into' => $new->id])
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($retired);
    }

    public function test_retiring_keeps_the_row_and_the_people_on_it(): void
    {
        $this->signInAsAdmin();

        $old = $this->row(MasterDataItem::DEPARTMENTS, 'Old');
        $this->row(MasterDataItem::DEPARTMENTS, 'New');
        $employee = $this->employeeOn($old, null);

        $this->post('/admin/master-data/departments/deactivate', ['item' => $old->id])
            ->assertSessionHasNoErrors();

        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame($old->id, $employee->fresh()->department_id);
    }

    public function test_a_merge_refuses_itself_another_list_and_a_retired_target(): void
    {
        $this->signInAsAdmin();

        $old = $this->row(MasterDataItem::DEPARTMENTS, 'Old');
        $retired = $this->row(MasterDataItem::DEPARTMENTS, 'Gone', active: false);
        $job = $this->row(MasterDataItem::DESIGNATIONS, 'Editor');

        foreach ([$old->id, $retired->id, $job->id] as $into) {
            $this->post('/admin/master-data/departments/merge', ['item' => $old->id, 'into' => $into])
                ->assertSessionHasErrors('into');
        }

        $this->assertTrue($old->fresh()->is_active);
    }

    public function test_other_lists_cannot_be_merged_and_staff_cannot_reach_it(): void
    {
        $this->signInAsAdmin();

        $this->get('/admin/master-data/leave-types/merge?item=1')->assertNotFound();

        $this->signInAsStaff(['employee', 'hr']);

        $old = $this->row(MasterDataItem::DEPARTMENTS, 'Old');
        $new = $this->row(MasterDataItem::DEPARTMENTS, 'New');

        $this->post('/admin/master-data/departments/merge', ['item' => $old->id, 'into' => $new->id]);

        $this->assertTrue($old->fresh()->is_active);
    }

    public function test_the_lists_and_forms_work_before_the_migration_is_applied(): void
    {
        $this->signInAsAdmin();

        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_11_02_000002_add_department_to_designations.php', '--force' => true]);

        $this->assertFalse(MasterDataDirectory::canPlaceDesignations());

        $this->get('/admin/master-data/designations')->assertOk()->assertDontSee('Not placed');
        $this->post('/admin/master-data/designations', ['name' => 'SDE', 'code' => 'SDE'])->assertSessionHasNoErrors();
        $this->post('/admin/master-data/designations/place', ['item' => 1])->assertNotFound();

        $this->signInAsStaff(['employee', 'hr']);

        $this->get('/employees/create')->assertOk()->assertSee('SDE');
    }

    public function test_the_migration_places_the_owners_designations_by_name_and_moves_nobody(): void
    {
        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_11_02_000002_add_department_to_designations.php', '--force' => true]);

        $rows = fn (string $list, string $name) => DB::table('master_data_items')->insertGetId([
            'list' => $list, 'name' => $name, 'code' => strtoupper(substr(md5($name), 0, 8)),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $visual = $rows(MasterDataItem::DEPARTMENTS, 'Visual Production');
        $editor = $rows(MasterDataItem::DESIGNATIONS, 'video editor');
        $unknown = $rows(MasterDataItem::DESIGNATIONS, 'Astronaut');

        Artisan::call('migrate', ['--force' => true]);

        $this->assertSame($visual, MasterDataItem::find($editor)->department_id);
        $this->assertNull(MasterDataItem::find($unknown)->department_id);
    }
}
