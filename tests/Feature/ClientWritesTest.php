<?php

namespace Tests\Feature;

use App\Mail\AccountInviteMail;
use App\Models\Client;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Clients — adding, editing, moving an engagement and handing over a login.
 *
 * The same shape Employees settled: the permission is on the route, every act
 * that changes a record is audited, and nothing destructive exists.
 */
class ClientWritesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE GUARD IS ON THE ROUTE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_list_itself_now_needs_a_permission(): void
    {
        /*
         * It had none while it was reading demo rows. The sidebar entry was
         * always gated on `clients.view`; typing the URL was the way around it.
         */
        $this->signInAsStaff(['employee']);

        $this->get('/clients')->assertForbidden();
    }

    public function test_somebody_who_may_see_clients_may_not_thereby_add_one(): void
    {
        // Support answers tickets and needs the client behind them. That is not
        // the same as signing one.
        $this->signInAsStaff(['employee', 'support']);

        $this->get('/clients')->assertOk();
        $this->get('/clients/create')->assertForbidden();
        $this->post('/clients', $this->validPayload())->assertForbidden();
    }

    public function test_the_four_write_permissions_are_separate(): void
    {
        $client = $this->aClient();

        $this->signInAsStaff(['employee']);
        $this->grant('clients.view', 'clients.edit');

        $this->get('/clients/'.$client->reference.'/edit')->assertOk();
        $this->get('/clients/create')->assertForbidden();
        $this->post('/clients/'.$client->reference.'/status', ['status' => 'inactive'])->assertForbidden();
        $this->post('/clients/'.$client->reference.'/invite', [
            'name' => 'Someone', 'email' => 'someone@example.test',
        ])->assertForbidden();
    }

    public function test_a_manager_may_do_all_four(): void
    {
        $client = $this->aClient();
        $this->signInAsStaff(['employee', 'manager']);

        $this->get('/clients/create')->assertOk();
        $this->get('/clients/'.$client->reference)->assertOk();
        $this->get('/clients/'.$client->reference.'/edit')->assertOk();
    }

    public function test_handing_over_a_login_is_a_sensitive_permission(): void
    {
        // Recording that a company exists is clerical. Giving somebody the
        // ability to read that company's invoices is not.
        $this->assertTrue(
            Permission::where('permission_key', 'clients.invite')->value('is_sensitive')
        );
        $this->assertFalse(
            (bool) Permission::where('permission_key', 'clients.create')->value('is_sensitive')
        );
    }

    /* ══════════════════════════════════════════════════════════════════════
       ADDING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_adding_a_client_records_it_and_gives_it_a_reference(): void
    {
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients', $this->validPayload())->assertRedirect();

        $client = Client::where('name', 'Northwind Trading')->first();

        $this->assertNotNull($client);
        $this->assertSame('CLT001', $client->reference);
        $this->assertSame('active', $client->status);
    }

    public function test_adding_a_client_creates_no_login(): void
    {
        /*
         * §1: every account is created by an administrator, deliberately. A
         * client record is not an account, and creating one silently would mean
         * somebody could read a company's invoices without anybody deciding
         * they should.
         */
        $this->signInAsStaff(['employee', 'manager']);

        $before = User::count();
        $this->post('/clients', $this->validPayload());

        $this->assertSame($before, User::count());
        Mail::assertNothingSent();
    }

    public function test_the_reference_is_derived_from_the_highest_and_never_reissued(): void
    {
        // From the highest existing one, not from a count: a count reissues a
        // reference the moment anything is removed, and a reference two clients
        // have held makes an invoice trail ambiguous.
        $this->seedDemoWorkforce();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients', $this->validPayload());

        $this->assertNotNull(Client::where('reference', 'CLT011')->first());
    }

    public function test_a_duplicate_name_is_refused(): void
    {
        // Two identical rows on a list cannot be told apart by whoever has to
        // pick one.
        $existing = $this->aClient();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients', $this->validPayload(['name' => $existing->name]))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Client::count());
    }

    public function test_a_status_outside_the_list_is_refused(): void
    {
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients', $this->validPayload(['status' => 'archived']))
            ->assertSessionHasErrors('status');
    }

    public function test_an_account_manager_must_be_active_staff(): void
    {
        /*
         * Somebody whose record was closed last month is not who a client
         * should be told to contact — and a client account is not staff at all,
         * so an id from the wrong realm must not be assignable either.
         */
        $closed = User::factory()->create([
            'user_id' => 'EMP901', 'account_type' => 'staff',
            'staff_kind' => 'employee', 'status' => 'inactive',
        ]);

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients', $this->validPayload(['account_manager_id' => $closed->id]))
            ->assertSessionHasErrors('account_manager_id');
    }

    public function test_adding_is_audited(): void
    {
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients', $this->validPayload());

        $entry = DB::table('audit_log')->where('action', AuditLog::CLIENT_CREATED)->first();

        $this->assertNotNull($entry);
        $this->assertSame('client', $entry->entity_type);
        $this->assertSame('CLT001', $entry->entity_id);
        $this->assertStringContainsString('Northwind Trading', (string) $entry->after_json);
    }

    /* ══════════════════════════════════════════════════════════════════════
       EDITING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_editing_records_what_it_was_before(): void
    {
        // "What changed" is the question somebody asks of an audit log, and an
        // entry with only the new value cannot answer it.
        $client = $this->aClient();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients/'.$client->reference, $this->validPayload([
            'name' => 'Renamed Client',
        ]))->assertRedirect();

        $entry = DB::table('audit_log')->where('action', AuditLog::CLIENT_UPDATED)->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('Original Client', (string) $entry->before_json);
        $this->assertStringContainsString('Renamed Client', (string) $entry->after_json);
        $this->assertSame('Renamed Client', $client->fresh()->name);
    }

    public function test_renaming_a_client_does_not_move_its_reference(): void
    {
        /*
         * Everything that will point at a client — invoices, tickets, projects,
         * and the portal accounts themselves — points at the reference. If a
         * rename moved it, renaming a company would detach its own records.
         */
        $client = $this->aClient();
        $account = $this->anAccountFor($client);

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients/'.$client->reference, $this->validPayload(['name' => 'Renamed Client']));

        $this->assertSame($client->reference, $client->fresh()->reference);
        $this->assertSame($client->reference, $account->fresh()->client_ref);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE ENGAGEMENT, AND THERE IS NO DELETE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_moving_the_engagement_is_audited(): void
    {
        $client = $this->aClient();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients/'.$client->reference.'/status', ['status' => 'inactive'])
            ->assertRedirect();

        $this->assertSame('inactive', $client->fresh()->status);

        $entry = DB::table('audit_log')->where('action', AuditLog::CLIENT_STATUS_CHANGED)->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('active', (string) $entry->before_json);
        $this->assertStringContainsString('inactive', (string) $entry->after_json);
    }

    public function test_setting_the_status_it_already_has_writes_nothing(): void
    {
        // A double-submitted form must not make it look as though somebody
        // changed the same value twice.
        $client = $this->aClient();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients/'.$client->reference.'/status', ['status' => 'active'])
            ->assertRedirect();

        $this->assertSame(0, DB::table('audit_log')->where('action', AuditLog::CLIENT_STATUS_CHANGED)->count());
    }

    public function test_completing_an_engagement_does_not_close_anybodys_login(): void
    {
        /*
         * A finished client still reads their old invoices. The engagement and
         * the account are different lifecycles, and conflating them would sign
         * somebody out of records they are entitled to.
         */
        $client = $this->aClient();
        $account = $this->anAccountFor($client);

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients/'.$client->reference.'/status', ['status' => 'inactive']);

        $this->assertSame('active', $account->fresh()->status);
    }

    public function test_there_is_no_delete_route(): void
    {
        $client = $this->aClient();
        $this->signInAsStaff(['employee', 'manager']);

        $this->delete('/clients/'.$client->reference)->assertStatus(405);
    }

    /* ══════════════════════════════════════════════════════════════════════
       PORTAL ACCESS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_inviting_creates_a_client_account_scoped_by_the_reference(): void
    {
        $client = $this->aClient();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients/'.$client->reference.'/invite', [
            'name' => 'Their Person',
            'email' => 'their.person@example.test',
        ])->assertRedirect();

        $account = User::where('email', 'their.person@example.test')->firstOrFail();

        $this->assertSame('client', $account->account_type);
        // No Employee base: a client has no attendance, leave or payslips (§2.1).
        $this->assertNull($account->staff_kind);
        // The reference, not the name. This column is the whole of the portal's
        // ownership rule (§6).
        $this->assertSame($client->reference, $account->client_ref);
    }

    public function test_nobody_types_the_clients_password(): void
    {
        $client = $this->aClient();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients/'.$client->reference.'/invite', [
            'name' => 'Their Person',
            'email' => 'their.person@example.test',
        ]);

        Mail::assertSent(AccountInviteMail::class, function (AccountInviteMail $mail) {
            return $mail->hasTo('their.person@example.test')
                && str_contains($mail->url, '/reset-password/');
        });
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        // The email is the sign-in identifier, so two accounts sharing one
        // would be two accounts a single credential could mean.
        $client = $this->aClient();
        $existing = User::factory()->create(['user_id' => 'EMP902', 'email' => 'taken@example.test']);

        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients/'.$client->reference.'/invite', [
            'name' => 'Their Person',
            'email' => $existing->email,
        ])->assertSessionHasErrors('email');
    }

    public function test_inviting_is_audited_as_its_own_action(): void
    {
        // "Who could see this client's invoices, and since when" is a question
        // an update entry cannot answer.
        $client = $this->aClient();
        $this->signInAsStaff(['employee', 'manager']);

        $this->post('/clients/'.$client->reference.'/invite', [
            'name' => 'Their Person',
            'email' => 'their.person@example.test',
        ]);

        $entry = DB::table('audit_log')->where('action', AuditLog::CLIENT_INVITED)->first();

        $this->assertNotNull($entry);
        $this->assertSame($client->reference, $entry->entity_id);
        $this->assertStringContainsString('their.person@example.test', (string) $entry->after_json);
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Northwind Trading',
            'industry' => 'Logistics',
            'country' => 'IN',
            'currency' => 'INR',
            'status' => 'active',
            'contact_name' => 'A Person',
            'contact_email' => 'a.person@example.test',
            'signed_on' => Carbon::now()->subMonth()->toDateString(),
        ];
    }

    protected function aClient(): Client
    {
        return Client::create([
            'reference' => 'CLT500',
            'name' => 'Original Client',
            'industry' => 'Education',
            'status' => 'active',
        ]);
    }

    protected function anAccountFor(Client $client): User
    {
        return User::factory()->create([
            'user_id' => 'CLI500',
            'account_type' => 'client',
            'staff_kind' => null,
            'status' => 'active',
            'client_ref' => $client->reference,
        ]);
    }

    /**
     * Give the signed-in account extra permissions without inventing a role.
     */
    protected function grant(string ...$permissions): void
    {
        $role = Role::firstOrCreate(
            ['role_key' => 'test_grant'],
            ['role_name' => 'Test grant', 'is_active' => true],
        );

        $role->permissions()->syncWithoutDetaching(
            Permission::whereIn('permission_key', $permissions)->pluck('id')
        );

        auth()->user()->roles()->syncWithoutDetaching([$role->id]);
        app(Rbac::class)->forget(auth()->user());
    }
}
