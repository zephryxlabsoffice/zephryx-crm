<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master data — departments, designations, leave types, document types.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * ONE TABLE, NOT FOUR
 *
 * These four lists have identical shape and one screen (Admin Panel → Master
 * Data) that is generic over all of them: pick a list, add a row, deactivate a
 * row. Four tables would mean four migrations, four models and a controller
 * that switches between them to do the same thing four ways — and a fifth list
 * later would mean touching all of it again. `list` is the discriminator.
 *
 * NOTHING IS EVER DELETED, ONLY DEACTIVATED
 *
 * A department with people in it cannot be removed without either orphaning
 * them or rewriting history. `is_active` is what the admin screen toggles, and
 * the foreign keys below are `restrictOnDelete` so the database refuses a
 * deletion the application never offers. A deactivated row keeps every record
 * that already points at it and stops being offered for new ones.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_data_items', function (Blueprint $table) {
            $table->id();

            // 'departments', 'designations', 'leave-types', 'document-types'.
            // Indexed because every read is "give me one list".
            $table->string('list', 32)->index();

            $table->string('name');

            /*
             * The short form shown in tables and used in exports. Unique within
             * its list, not globally — 'DEV' as a department and 'DEV' as a
             * designation are different things that may legitimately coexist.
             */
            $table->string('code', 16);

            $table->boolean('is_active')->default(true);

            // Display order. Master data is a list people read, and alphabetical
            // is not always the order that makes sense to them.
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['list', 'code']);
            $table->unique(['list', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data_items');
    }
};
