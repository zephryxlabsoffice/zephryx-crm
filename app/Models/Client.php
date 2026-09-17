<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An organisation this company works for (foundation spec §12).
 *
 * The client, not the login. Portal accounts are `users` rows pointing here
 * through `client_ref` — see `accounts()` and the head of the migration for why
 * the two are separate.
 */
class Client extends Model
{
    /**
     * The engagement states, in the order the filter offers them.
     *
     * Declared here rather than in the presenter because the database enum, the
     * validation rule and the dropdown all have to agree, and three copies of a
     * list is two chances for them to stop agreeing.
     *
     * @var list<string>
     */
    public const STATUSES = ['active', 'pending', 'review', 'on_hold', 'completed'];

    protected $fillable = [
        'reference', 'name', 'industry', 'status',
        'contact_name', 'contact_email', 'contact_phone', 'billing_address',
        // A key into the private disk, never a URL. See the migration.
        'photo_path',
        'account_manager_id', 'signed_on', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'signed_on' => 'date',
        ];
    }

    /**
     * The portal accounts belonging to this client.
     *
     * Keyed on `reference`, not on the primary key, because that is the column
     * every ownership check in the portal resolves through (§6) and a second
     * join path would be a second answer to "whose data is this".
     *
     * @return HasMany<User, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(User::class, 'client_ref', 'reference');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    /**
     * The reference is what URLs carry, so this is what route binding reads.
     */
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * Clients still being worked with.
     *
     * @param  Builder<Client>  $query
     * @return Builder<Client>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
