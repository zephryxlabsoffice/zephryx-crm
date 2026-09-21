<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The one Google connection this company has. See the migration for why this
 * is its own table and not a `company_settings` row.
 *
 * Nothing outside App\Http\Controllers\Admin\IntegrationsController and
 * App\Support\Google reads `service_account_key` — the whole point of keeping
 * it off `config()` is that reading it anywhere else recreates the leak this
 * table exists to close.
 */
class GoogleConnection extends Model
{
    protected $table = 'google_connection';

    protected $fillable = [
        'service_account_email', 'service_account_key', 'key_fingerprint',
        'calendar_id', 'impersonate_email', 'shared_drive_id',
        'connected_at', 'connected_by',
    ];

    protected function casts(): array
    {
        return [
            'service_account_key' => 'encrypted',
            'connected_at' => 'datetime',
        ];
    }

    /**
     * The one row, created on first save rather than forced to id 1 — so a
     * fresh install with nothing connected yet still gets a usable, unsaved
     * instance instead of a special-cased null.
     */
    public static function current(): self
    {
        return static::query()->first() ?? new self;
    }

    public function isConnected(): bool
    {
        return $this->service_account_key !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }
}
