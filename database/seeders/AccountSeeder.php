<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Support\Demo\DemoClients;
use App\Support\Demo\DemoEmployees;
use App\Support\Realm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * The accounts.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHAT THIS SEEDS IN PRODUCTION: ONE ACCOUNT, AND NOT ITS PASSWORD
 *
 * The owner account has to exist, or there is no way to configure the system —
 * no roles to assign, nobody to assign them. Everything else is created by an
 * administrator through the Admin Panel, because §1 says there is no public
 * sign-up and every account is created by one.
 *
 * The password comes from the environment and the seeder REFUSES TO RUN in
 * production without it. A default password on the most powerful account in the
 * application is the single worst thing a seeder can leave behind: it survives
 * every deploy, it is in a public repository, and nobody notices it because the
 * account works perfectly.
 *
 * The demo staff and client accounts below exist only in local + debug, for the
 * same reason every Demo class does.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class AccountSeeder extends Seeder
{
    /**
     * The development password, and it is deliberately obvious.
     *
     * A weak, well-known string is safer here than a plausible-looking one:
     * nobody can mistake it for a real credential, and it can only ever exist
     * on accounts that are themselves local + debug only.
     */
    public const DEV_PASSWORD = 'zephryx-dev-password';

    public function run(): void
    {
        $this->owner();

        if (! $this->demoEnabled()) {
            return;
        }

        $this->staff();
        $this->clients();
    }

    protected function demoEnabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * The single admin account (§2.1) — a configuration surface, not a person.
     */
    protected function owner(): void
    {
        $password = env('ZEPHRYX_OWNER_PASSWORD');

        if (blank($password)) {
            if (app()->environment('production')) {
                throw new RuntimeException(
                    'ZEPHRYX_OWNER_PASSWORD is not set. The owner account will not be seeded with a '
                    .'default password — see database/seeders/AccountSeeder.'
                );
            }

            $password = self::DEV_PASSWORD;
        }

        User::updateOrCreate(
            ['user_id' => 'OWNER'],
            [
                'name' => config('zephryx.brand.name').' Admin',
                'email' => config('zephryx.support.email'),
                'password' => $password,
                'account_type' => Realm::ADMIN,
                // No Employee base, no personal records, no operational
                // authority (§2.1). staff_kind stays null for exactly that.
                'staff_kind' => null,
                'status' => 'active',
                'password_changed_at' => Carbon::now(),
            ],
        );
    }

    /**
     * Staff accounts, from the same demo people the pages already show.
     *
     * Drawn from DemoEmployees rather than invented so the seeded accounts and
     * the demo directory agree — a signed-in Amit Verma seeing somebody else's
     * name on his own attendance page would be a confusing thing to review.
     */
    protected function staff(): void
    {
        $roles = Role::pluck('id', 'role_key');

        foreach (DemoEmployees::all() as $employee) {
            $user = User::updateOrCreate(
                ['user_id' => $employee['user_id']],
                [
                    'name' => $employee['name'],
                    'email' => $employee['email'],
                    'password' => self::DEV_PASSWORD,
                    'account_type' => Realm::STAFF,
                    // Everyone here is an employee. A Mentor account would set
                    // this to `mentor` and lose the Employee base with it —
                    // that single column is the whole difference (§2.1).
                    'staff_kind' => 'employee',
                    'status' => $employee['status'] === 'inactive' ? 'inactive' : 'active',
                    'password_changed_at' => Carbon::now(),
                ],
            );

            $assigned = collect($this->rolesFor($employee['user_id']))
                ->map(fn (string $key) => $roles[$key] ?? null)
                ->filter()
                ->all();

            $user->roles()->syncWithoutDetaching(
                collect($assigned)->mapWithKeys(fn (int $id) => [
                    $id => ['assigned_at' => Carbon::now()],
                ])->all()
            );
        }
    }

    /**
     * Who holds which roles.
     *
     * Deliberately shows stacking (§2.4) — Pooja is HR and an Employee, Rahul
     * is a Manager and an Employee — because a seed where everybody has exactly
     * one role hides the union rule until it bites in production.
     *
     * @return list<string>
     */
    protected function rolesFor(string $employeeId): array
    {
        return match ($employeeId) {
            'EMP001' => ['employee', 'team_lead'],
            'EMP004' => ['employee', 'manager'],
            'EMP005' => ['employee', 'hr'],
            'EMP006' => ['employee', 'manager'],
            'EMP007' => ['employee', 'support'],
            'EMP011' => ['employee', 'hr'],
            default => ['employee'],
        };
    }

    /**
     * Client accounts.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * `client_ref` HOLDS THE CLIENT'S REFERENCE, NOT ITS NAME
     *
     * It is the column every ownership check in the portal resolves through
     * (§6), which makes it an identifier — and a name is not one. Two companies
     * can share a name, and renaming one would silently detach its portal from
     * its own invoices, tickets and projects.
     *
     * It held the name until the `clients` table existed and there was nothing
     * else for it to hold. ClientSeeder runs before this one so there is.
     * ─────────────────────────────────────────────────────────────────────────
     */
    protected function clients(): void
    {
        foreach (DemoClients::all() as $index => $demo) {
            $client = Client::where('name', $demo['name'])->first();

            if ($client === null) {
                continue;
            }

            $slug = str($client->name)->slug()->value();

            User::updateOrCreate(
                ['user_id' => 'CLI'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'name' => $client->name,
                    'email' => $slug.'@example.com',
                    'password' => self::DEV_PASSWORD,
                    'account_type' => Realm::CLIENT,
                    'staff_kind' => null,
                    // The ENGAGEMENT being finished does not close the login:
                    // a completed client still reads their old invoices. This
                    // seed keeps them signed-in-able for exactly that reason.
                    'status' => 'active',
                    'client_ref' => $client->reference,
                    'password_changed_at' => Carbon::now(),
                ],
            );
        }
    }
}
