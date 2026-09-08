<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The employment record behind a staff account.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHY THIS IS NOT MORE COLUMNS ON `users`
 *
 * Three of these columns are meaningless for two of the three realms. A client
 * has no department; the admin account has no joining date and no birthday. Put
 * on `users`, every one of them would be null for every non-staff account, and
 * "null because it does not apply" and "null because nobody filled it in" would
 * become the same value — which is exactly the ambiguity that later turns into
 * a report quietly counting the admin account as an employee.
 *
 * The account and the employment are also different lifecycles: §2.1 has a
 * Mentor who holds an account with no Employee base at all.
 *
 * WHAT IS NOT HERE: `status`
 *
 * The demo rows carried active / on_leave / inactive in one field. Two of those
 * are account state (`users.status`) and the third is not a state at all — it
 * is a question about today, answered by whether an approved leave request
 * covers it. Stored, it would be a column somebody has to remember to change
 * twice per absence, and it would be wrong the morning somebody's leave ends.
 * `Employee::onLeave()` derives it.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            /*
             * One employment record per account. Restricted rather than
             * cascading: an employee who leaves is deactivated, never deleted,
             * because attendance, payroll and the audit log all point back here
             * and a cascade would silently take years of records with it.
             */
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();

            // Master data (§ Admin Panel). Nullable because an account can be
            // created before somebody decides where the person sits.
            $table->foreignId('department_id')->nullable()
                ->constrained('master_data_items')->restrictOnDelete();
            $table->foreignId('designation_id')->nullable()
                ->constrained('master_data_items')->restrictOnDelete();

            $table->date('joined_on')->nullable();

            /*
             * Stored in full and displayed as a day and a month only — see
             * App\Support\Milestones. A colleague needs to know when to say
             * happy birthday, not how old somebody is, and putting everybody's
             * age on an internal page permanently is harder to take back than
             * to not do.
             */
            $table->date('date_of_birth')->nullable();

            // The per-person opt-out. Not everyone wants their birthday on a
            // company board, and the default is the answer somebody who was
            // never asked would want least to be surprised by.
            $table->boolean('announce_milestones')->default(true);

            $table->timestamps();

            // The directory's default ordering and the donut's grouping.
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
