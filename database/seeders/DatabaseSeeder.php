<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The order matters and it is the only thing this class decides.
 *
 * Roles cannot be granted permissions that do not exist, and accounts cannot be
 * given roles that do not exist. Both seeders are idempotent, so running this
 * again after adding a module adds the new keys and leaves every existing grant
 * alone — a seeder that silently revoked access on deploy would be invisible
 * until somebody needed the thing it took away.
 *
 * Laravel's stock "Test User" is gone. It had no user_id, no realm and no
 * status, which now means an account that cannot be placed in any realm and
 * cannot sign in — and one more thing in the users table that nobody put there
 * on purpose.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        /*
         * MasterDataSeeder sits between them because it runs in production like
         * the other two and, unlike them, the modules above it cannot function
         * without its rows — an employee form with no departments to choose
         * from creates nobody. EmployeeSeeder is last because it needs both the
         * accounts and the departments to attach them to. It and ClientSeeder
         * are the two that do nothing at all outside local + debug.
         */
        /*
         * ClientSeeder is before AccountSeeder because the client accounts it
         * creates point at those rows through `client_ref` — an account whose
         * client does not exist is a portal session scoped to nothing.
         */
        $this->call([
            RbacSeeder::class,
            MasterDataSeeder::class,
            ClientSeeder::class,
            AccountSeeder::class,
            EmployeeSeeder::class,
            // Teams need the employment records; projects need the teams, the
            // clients and the people, so it goes last of all.
            TeamSeeder::class,
            ProjectSeeder::class,
            TaskSeeder::class,
            // Leave before Attendance: approved leave is what stops a day being
            // drawn as an absence, and the attendance fixture skips those days.
            LeaveSeeder::class,
            AttendanceSeeder::class,
            SalarySeeder::class,
            TicketSeeder::class,
            InvoiceSeeder::class,
            MeetingSeeder::class,
            AnnouncementSeeder::class,
        ]);
    }
}
