<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file on a task.
 *
 * `document_path`/`document_name`/`document_bytes` per row rather than the
 * single-document columns Invoices carries — a task can hold several files at
 * once. Storage always goes through App\Support\Documents\DocumentStore::put(),
 * which is always Google Drive for an uploaded file (decided 2026-09-16).
 *
 * No delete route: matches the rest of this module (and Clients, Teams,
 * Projects) — nothing here is destructive, only added to.
 */
class TaskAttachment extends Model
{
    protected $fillable = [
        'task_id', 'uploaded_by', 'uploaded_by_label',
        'document_path', 'document_name', 'document_bytes',
    ];

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
