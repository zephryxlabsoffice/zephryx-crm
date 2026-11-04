<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a report: an assigned task, or, with no `task_id`, work nobody assigned.
 *
 * `title` is copied so the report still reads the same if the task is renamed or
 * deleted later.
 */
class EodItem extends Model
{
    protected $fillable = ['eod_report_id', 'task_id', 'project_id', 'title', 'status', 'comment'];

    /**
     * @return BelongsTo<EodReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(EodReport::class, 'eod_report_id');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
