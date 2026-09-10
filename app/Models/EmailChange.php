<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sign-in address change in flight.
 *
 * Written and read by App\Support\Auth\EmailChanges, which is the only place
 * that knows how to turn a token into one of these. Nothing else should query
 * it by token — see the head of that class for why.
 */
class EmailChange extends Model
{
    protected $fillable = [
        'user_id', 'new_email',
        'old_token_hash', 'new_token_hash',
        'old_confirmed_at', 'new_confirmed_at',
        'expires_at', 'completed_at', 'cancelled_at', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_confirmed_at' => 'datetime',
            'new_confirmed_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Both halves confirmed. The only condition under which the address moves.
     */
    public function isConfirmed(): bool
    {
        return $this->old_confirmed_at !== null && $this->new_confirmed_at !== null;
    }

    public function isSpent(): bool
    {
        return $this->completed_at !== null || $this->cancelled_at !== null;
    }

    /**
     * Not applied, not abandoned, not expired.
     *
     * @param  Builder<EmailChange>  $query
     * @return Builder<EmailChange>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now());
    }
}
