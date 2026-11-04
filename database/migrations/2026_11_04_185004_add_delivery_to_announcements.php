<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telling people when a post goes up. See App\Support\AnnouncementDelivery.
 *
 * `delivery_pending` defaults to false on purpose: every post already on the
 * board stays quiet, rather than everybody being told about all of them at once
 * the first night after this is applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('notify_email')->default(false)->after('for_clients');
            $table->boolean('delivery_pending')->default(false)->after('notify_email');
            $table->index('delivery_pending');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['delivery_pending']);
            $table->dropColumn(['notify_email', 'delivery_pending']);
        });
    }
};
