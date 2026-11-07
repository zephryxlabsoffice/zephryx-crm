<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\Realm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The profile photo showed only on the Employees page and the top bar (owner,
 * 2026-11-07). Now it is beside the person's name wherever it appears.
 */
class PhotosEverywhereTest extends TestCase
{
    public function test_a_photo_shows_beside_the_name_on_a_ticket_and_any_colleague_may_load_it(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('employees/photos/face.png', 'png-bytes');

        $withPhoto = $this->person('EMP961', ['employee', 'support']);
        EmployeeProfile::create(['employee_id' => $withPhoto->id, 'photo_path' => 'employees/photos/face.png']);
        $raiser = $this->person('EMP962', ['employee']);

        $ticket = Ticket::create([
            'reference' => 'TKT-2026-961', 'type' => 'internal', 'subject' => 'Printer', 'description' => 'Jammed.',
            'raised_by' => $raiser->id, 'assignee_id' => $withPhoto->id, 'status' => 'open',
        ]);
        TicketComment::create([
            'ticket_id' => $ticket->id, 'author_id' => $withPhoto->user_id, 'author_label' => 'Has A Photo',
            'author_role' => 'Support', 'body' => 'On it.', 'visibility' => TicketComment::PUBLIC,
        ]);

        // A plain employee: no employees.view, so not the Employees page's route.
        $this->actingAs($raiser->user);

        $page = $this->get('/tickets/TKT-2026-961')->assertOk();
        $page->assertSee('/people/EMP961/photo?v=', false);
        $this->assertSame(3, substr_count($page->getContent(), '/people/EMP961/photo?v='), 'header, people panel and thread');

        $this->get('/people/EMP961/photo')->assertOk()->assertHeader('Cache-Control', 'max-age=86400, private');
        $this->get('/employees/EMP961/photo')->assertForbidden();
        $this->get('/people/EMP962/photo')->assertNotFound();
    }

    /**
     * @param  list<string>  $roles
     */
    protected function person(string $staffId, array $roles): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'name' => $staffId === 'EMP961' ? 'Has A Photo' : 'Raised It',
            'account_type' => Realm::STAFF,
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);
        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }
}
