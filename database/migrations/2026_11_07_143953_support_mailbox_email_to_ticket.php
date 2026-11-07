<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email to ticket (owner, 2026-11-06): mail to support@ becomes a ticket.
 *
 * - `support_mailbox`: one row. The Google sign-in for the mailbox support@ is
 *   delivered to (contact@), read-only, and the keywords that pick a category.
 * - `support_mail_messages`: every message looked at, once, and what became of
 *   it. The unique Gmail id is what stops a message being made into two tickets.
 * - On a ticket: where it came from, and who sent it when they have no account.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_mailbox')) {
            Schema::create('support_mailbox', function (Blueprint $table) {
                $table->id();
                // The address tickets are sent to, and replies are sent from.
                $table->string('address', 190)->nullable();
                $table->string('client_id', 255)->nullable();
                $table->text('client_secret')->nullable();
                $table->text('refresh_token')->nullable();
                // The Google account signed in as (contact@).
                $table->string('account_email', 190)->nullable();
                $table->text('keywords')->nullable();
                $table->timestamp('connected_at')->nullable();
                $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('last_checked_at')->nullable();
                $table->string('last_error', 1000)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_mail_messages')) {
            Schema::create('support_mail_messages', function (Blueprint $table) {
                $table->id();
                $table->string('gmail_id', 64)->unique();
                $table->string('thread_id', 64)->nullable()->index();
                $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
                $table->string('from_email', 190)->nullable()->index();
                $table->string('subject', 255)->nullable();
                // ticket, reply, ignored, failed
                $table->string('outcome', 16);
                $table->string('note', 255)->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'source')) {
                // crm, portal or email
                $table->string('source', 16)->nullable();
                $table->string('requester_email', 190)->nullable();
                $table->string('requester_name', 190)->nullable();
                $table->string('mail_thread_id', 64)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_mail_messages');
        Schema::dropIfExists('support_mailbox');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['mail_thread_id']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['source', 'requester_email', 'requester_name', 'mail_thread_id']);
        });
    }
};
