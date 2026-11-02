<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One browser one person has signed in from. See App\Support\Auth\UserDevices.
 */
class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'browser_hash',
        'device_name',
        'user_agent',
        'ip_address',
        'location',
        'sign_in_token',
        'signed_in_at',
        'last_seen_at',
    ];

    protected $hidden = ['browser_hash', 'sign_in_token'];

    protected function casts(): array
    {
        return [
            'signed_in_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
