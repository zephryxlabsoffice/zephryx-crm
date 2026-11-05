<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A manager or team lead saying a missing End of Day is urgent. See App\Support\EodUrgent.
 *
 * One per person per date. It stays after the report is filed, so the report can say
 * it was asked for. `requested_by` is an account, because a company account holding
 * the permission has no employee record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eod_urgent_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'report_date']);
            $table->index(['employee_id', 'fulfilled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eod_urgent_requests');
    }
};
