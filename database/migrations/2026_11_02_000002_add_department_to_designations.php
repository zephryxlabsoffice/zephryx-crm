<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A designation belongs to one department (owner, 2026-11-02).
 *
 * The owner's designations are placed by name, as they asked Claude to decide
 * where each one goes. Names not found here stay unplaced for Admin > Master
 * data, and no employee record is moved: this changes the lists, not the people.
 */
return new class extends Migration
{
    /** @var array<string, string> Designation name => department name, lowercased. */
    protected const PLACEMENT = [
        'technical associate' => 'it',
        'hr' => 'hr',
        'hr executive' => 'hr',
        'accountant' => 'accounts',
        'sde' => 'development',
        'frontend developer' => 'development',
        'backend developer' => 'development',
        'content researcher' => 'content',
        'social media manager' => 'content',
        'video editor' => 'visual production',
        'visual strategist' => 'visual production',
        'ui/ux designer' => 'visual production',
        'motion designer' => 'visual production',
    ];

    public function up(): void
    {
        Schema::table('master_data_items', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('code')
                ->constrained('master_data_items')->nullOnDelete();
        });

        $departments = DB::table('master_data_items')
            ->where('list', 'departments')
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->mapWithKeys(fn (object $row) => [mb_strtolower(trim($row->name)) => $row->id]);

        $designations = DB::table('master_data_items')->where('list', 'designations')->get(['id', 'name']);

        foreach ($designations as $designation) {
            $departmentName = self::PLACEMENT[mb_strtolower(trim($designation->name))] ?? null;

            if ($departmentName !== null && isset($departments[$departmentName])) {
                DB::table('master_data_items')
                    ->where('id', $designation->id)
                    ->update(['department_id' => $departments[$departmentName]]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('master_data_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
