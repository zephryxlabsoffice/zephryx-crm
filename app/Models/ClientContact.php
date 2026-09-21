<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One more person at a client, beyond the main contact.
 *
 * The main contact stays `clients.contact_*` — the one that has always been
 * editable from the client's own portal (`Client\ProfileController::
 * EDITABLE`). This table is "several contacts, one login" (decided
 * 2026-09-11): the client adds and removes rows here themselves; the login
 * itself is unaffected either way, because one account still sees everything
 * for that client regardless of how many people are named on it.
 */
class ClientContact extends Model
{
    protected $fillable = ['client_id', 'name', 'email', 'phone'];

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
