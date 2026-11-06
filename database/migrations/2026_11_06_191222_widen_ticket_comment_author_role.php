<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A comment copies its author's roles onto the row, joined with " · ". Forty
 * characters held one or two roles, and somebody holding four ("Employee · HR ·
 * Team Lead · System Administrator") could not comment at all: the insert failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->string('author_role', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->string('author_role', 40)->change();
        });
    }
};
