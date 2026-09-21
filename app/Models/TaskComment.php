<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One note on a task.
 *
 * No visibility column, unlike TicketComment: a task has one readership —
 * staff — so there is no audience to get wrong. The author's name is still
 * copied at write time, for the same reason the audit log and ticket threads
 * do it: a thread whose author's account was later removed must not read
 * "somebody said".
 */
class TaskComment extends Model
{
    protected $fillable = ['task_id', 'author_id', 'author_label', 'body'];

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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
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
            'at' => $this->created_at,
            'body' => $this->body,
        ];
    }
}
