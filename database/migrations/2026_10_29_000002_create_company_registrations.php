<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * verify.zephryxlabs.in (2026-10-29): the company's registration IDs - type,
 * number, optional document - shown on every verify page. See
 * App\Models\CompanyRegistration.
 *
 * Its own migration because the live server had already run
 * 2026_10_29_000001_verify_site before this table was added there, so it
 * never got the table. The check keeps it safe on a server that did.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_registrations')) {
            return;
        }

        Schema::create('company_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('number', 100);
            $table->string('document_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_registrations');
    }
};
