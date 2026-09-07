<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The RBAC engine's tables (foundation spec §5, §8).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * PERMISSIONS ARE KEYS, AND RANK IS A SEPARATE TABLE
 *
 * Two shapes here carry the whole authorisation model, and both were decided in
 * the spec rather than here.
 *
 * `role_permissions` is a plain join, so a person's permissions are the UNION
 * across their roles (§2.4). There is no precedence column and no "deny" row,
 * because union resolution needs neither: gaining a role can only ever add.
 * The moment a deny exists, every question becomes "which rule won", and that
 * is the class of bug nobody can reason about at three in the morning.
 *
 * `role_domain_rank` is separate from permissions because rank is NOT a
 * permission (§2.5). It answers "may I act on this person" and routes
 * approvals, and it is per domain because HR outranks the System Administrator
 * in people and finance while being outranked in system. A single
 * `hierarchy_level` integer on the role cannot express that, which is exactly
 * why there is a table.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_key', 64)->unique();
            $table->string('role_name');
            $table->string('description')->nullable();
            // Support Manager / L3 / L2 / L1 hang off Support Associate (§2.3).
            $table->foreignId('parent_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('permission_key', 128)->unique();
            $table->string('permission_name');
            // `leave`, `salary`, `client`, … — what the Access Control screen
            // groups by, stored so the grouping does not depend on parsing.
            $table->string('module', 64)->index();
            $table->string('description')->nullable();
            /*
             * The ones no role should hold casually. A column rather than a
             * hardcoded list, so marking a new permission sensitive is data
             * rather than a deploy.
             */
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();

            // The pair is the row. A duplicate grant is not a different grant,
            // and a union does not care how many times something was added.
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();

            /*
             * Who assigned it and when (§8). The column only means something if
             * assignment happens somewhere with an accountable actor, which is
             * why role assignment lives in the Admin Panel and not in the HR
             * directory.
             *
             * nullOnDelete, not cascade: an assignment must not disappear
             * because the person who made it left. The record of what happened
             * outlives the people in it.
             */
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();

            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain_key', 32)->unique();
            $table->string('domain_name');
        });

        Schema::create('role_domain_rank', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            // Higher is more authority. Zero means no standing in this domain,
            // which is what a Mentor has everywhere.
            $table->unsignedSmallInteger('rank')->default(0);

            $table->primary(['role_id', 'domain_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_domain_rank');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
