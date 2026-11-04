<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * End of day reports. See App\Http\Controllers\EodController.
 *
 * One report per person per date. A line with no `task_id` is work nobody assigned.
 * Who it was sent to is stored rather than worked out again, so somebody moving
 * teams later does not change who could read what was already sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eod_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('report_date');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['employee_id', 'report_date']);
            $table->index('report_date');
        });

        Schema::create('eod_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eod_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->string('status', 32);
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('eod_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eod_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['eod_report_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eod_recipients');
        Schema::dropIfExists('eod_items');
        Schema::dropIfExists('eod_reports');
    }
};
