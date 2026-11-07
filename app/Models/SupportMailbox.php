<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The mailbox support@ is delivered to, signed in to read-only (owner,
 * 2026-11-06). One row. The secret and the token are encrypted and never sent to
 * a page. See App\Support\SupportMail\SupportMailIntake.
 */
class SupportMailbox extends Model
{
    protected $table = 'support_mailbox';

    /**
     * The keywords a new installation starts with: "Category: word, word" per line.
     * A category that is not in Admin > Master data is skipped.
     */
    public const DEFAULT_KEYWORDS = "Access: password, login, log in, sign in, otp, locked out, access\n"
        ."Network: network, internet, wifi, wi-fi, vpn, connection\n"
        ."Bug: error, bug, broken, not working, crash, fails, failed\n"
        ."Billing: invoice, payment, bill, refund, receipt\n"
        ."Change request: change request, change, modify, redesign\n"
        ."Feature request: new feature, feature request, add a feature\n"
        ."Performance: slow, slowness, takes too long, performance\n"
        .'Attendance: attendance, check in, check-in, check out, check-out, checkout';

    protected $fillable = [
        'address', 'client_id', 'client_secret', 'refresh_token', 'account_email',
        'keywords', 'connected_at', 'connected_by', 'last_checked_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'refresh_token' => 'encrypted',
            'connected_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public static function ready(): bool
    {
        try {
            return Schema::hasTable('support_mailbox');
        } catch (Throwable) {
            return false;
        }
    }

    public static function current(): self
    {
        return static::query()->first() ?? new self(['keywords' => self::DEFAULT_KEYWORDS]);
    }

    public function hasClient(): bool
    {
        return filled($this->client_id) && filled($this->client_secret);
    }

    public function isConnected(): bool
    {
        return $this->hasClient() && filled($this->refresh_token) && filled($this->address);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }
}
