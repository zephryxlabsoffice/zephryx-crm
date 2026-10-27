<?php

namespace App\Support\Admin;

use App\Models\Employee;
use App\Models\User;
use App\Support\Documents\DocumentStore;
use App\Support\Realm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a staff account that was made by mistake (owner, 2026-10-27).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE EXCEPTION TO "CLOSED, NEVER REMOVED"
 *
 * Records are closed, never deleted — somebody's attendance and payslips must
 * outlive their account. But an account created with a typo in the email has
 * no history to protect, and while it exists it holds that email, so the
 * right account cannot be made. So deletion exists for exactly that case:
 *
 *   - a STAFF account (employee or normal), never the owner, never a client
 *   - that has NEVER signed in
 *   - and that nothing in the database points at, apart from what was written
 *     when it was set up (roles, profile, bank and pay agreement, invite links)
 *
 * The "points at" check reads the database's own foreign keys rather than a
 * list kept here, so a table added later is checked without anybody
 * remembering to add it. Anything that is not setup data blocks the delete,
 * and the page says what.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AccountRemoval
{
    /**
     * Written when an account is set up, and removed with it. Keyed by the
     * table, valued by the column that points at the account.
     */
    protected const SETUP_ROWS = [
        'users' => [
            'user_roles' => 'user_id',
            'password_resets' => 'user_id',
            'remember_tokens' => 'user_id',
            'trusted_devices' => 'user_id',
            'two_factor_codes' => 'user_id',
            'email_changes' => 'user_id',
            'notifications' => 'user_id',
            'login_attempts' => 'user_id',
        ],
        'employees' => [
            'employee_profiles' => 'employee_id',
            'employee_banking' => 'employee_id',
            'employee_salary_structures' => 'employee_id',
        ],
    ];

    public function __construct(protected DocumentStore $documents) {}

    /**
     * Why this account cannot be deleted — an empty list means it can.
     *
     * @return list<string>
     */
    public function blockers(User $user): array
    {
        if ($user->account_type !== Realm::STAFF) {
            return ['Only staff accounts can be deleted here.'];
        }

        $reasons = [];

        if ($user->last_login_at !== null) {
            $reasons[] = 'It has been signed in to.';
        }

        $employee = Employee::where('user_id', $user->id)->first();

        foreach ($this->references($user, $employee) as $table => $count) {
            $reasons[] = ucfirst(str_replace('_', ' ', $table)).' ('.$count.')';
        }

        return $reasons;
    }

    /**
     * Remove the account and its setup rows. Call only when blockers() is empty.
     */
    public function delete(User $user): void
    {
        $employee = Employee::with('profile')->where('user_id', $user->id)->first();
        $photo = $employee?->profile?->photo_path;

        DB::transaction(function () use ($user, $employee) {
            if ($employee !== null) {
                foreach (self::SETUP_ROWS['employees'] as $table => $column) {
                    DB::table($table)->where($column, $employee->id)->delete();
                }

                $employee->delete();
            }

            foreach (self::SETUP_ROWS['users'] as $table => $column) {
                DB::table($table)->where($column, $user->id)->delete();
            }

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            $user->delete();
        });

        if ($photo !== null) {
            $this->documents->forget($photo);
        }
    }

    /**
     * Rows anywhere that point at this account or its employment, outside
     * the setup data. Table => count.
     *
     * @return array<string, int>
     */
    protected function references(User $user, ?Employee $employee): array
    {
        $targets = ['users' => $user->id];

        if ($employee !== null) {
            $targets['employees'] = $employee->id;
        }

        $found = [];

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];

            foreach (Schema::getForeignKeys($name) as $fk) {
                $target = $fk['foreign_table'];

                if (! isset($targets[$target]) || count($fk['columns']) !== 1) {
                    continue;
                }

                $column = $fk['columns'][0];

                // The account's own employment row, and its setup data.
                if (($name === 'employees' && $column === 'user_id')
                    || (self::SETUP_ROWS[$target][$name] ?? null) === $column) {
                    continue;
                }

                $count = DB::table($name)->where($column, $targets[$target])->count();

                if ($count > 0) {
                    $found[$name] = ($found[$name] ?? 0) + $count;
                }
            }
        }

        return $found;
    }
}
