<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The answers an announcement was written from, so it can be edited.
 *
 * `body` is the finished text people read. Editing a closure date has to rewrite
 * the sentence that states it, so the message and the structured answers are kept
 * apart here. Null on anything posted before this column existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->json('details')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
