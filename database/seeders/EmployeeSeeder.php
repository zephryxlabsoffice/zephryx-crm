<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\User;
use App\Support\Demo\DemoEmployees;
use Illuminate\Database\Seeder;

/**
 * Employment records for the demo staff accounts. Local + debug only.
 *
 * AccountSeeder creates the accounts from the same DemoEmployees rows; this
 * gives each of them the employment behind it, so a signed-in Amit Verma sees
 * his own department on his own profile rather than a blank.
 *
 * Nothing here runs in production: real employees are created through the
 * Employees module by somebody who knows who they are.
 */
class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        $departments = MasterDataItem::inList(MasterDataItem::DEPARTMENTS)->pluck('id', 'name');
        $designations = MasterDataItem::inList(MasterDataItem::DESIGNATIONS)->pluck('id', 'name');

        foreach (DemoEmployees::all() as $row) {
            $user = User::where('user_id', $row['user_id'])->first();

            if ($user === null) {
                continue;
            }

            Employee::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'department_id' => $departments[$row['department']] ?? null,
                    'designation_id' => $designations[$row['designation']] ?? null,
                    'joined_on' => $row['joined'],
                    'date_of_birth' => $row['dob'],
                    'announce_milestones' => $row['announce_milestones'],
                ],
            );
        }
    }
}
