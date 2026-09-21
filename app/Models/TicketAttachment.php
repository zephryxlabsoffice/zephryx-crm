<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file on a ticket. See App\Models\TaskAttachment — same shape, same
 * reasoning, one row per file rather than a single-document column.
 */
class TicketAttachment extends Model
{
    protected $fillable = [
        'ticket_id', 'uploaded_by', 'uploaded_by_label',
        'document_path', 'document_name', 'document_bytes',
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
