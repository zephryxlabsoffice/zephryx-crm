<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reply on a ticket.
 *
 * The author's name and role are copied at write time beside the account id,
 * for the same reason the audit log does it: a thread whose author's account
 * was later removed must not read "somebody said".
 */
class TicketComment extends Model
{
    public const PUBLIC = 'public';

    public const INTERNAL = 'internal';

    protected $fillable = [
        'ticket_id', 'author_id', 'author_label', 'author_role', 'body', 'visibility',
    ];

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isInternal(): bool
    {
        return $this->visibility === self::INTERNAL;
    }

    /**
     * The shape the thread template reads.
     *
     * @return array<string, mixed>
     */
    public function toRecordArray(): array
    {
        return [
            'author' => $this->author_label,
            'role' => $this->author_role,
            'visibility' => $this->visibility,
            'at' => $this->created_at,
            'body' => $this->body,
        ];
    }
}
