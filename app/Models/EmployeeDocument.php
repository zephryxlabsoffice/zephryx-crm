<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file held against a person.
 *
 * The row is the record; the file is on the private disk and is reachable only
 * through App\Support\Documents\DocumentStore. There is no `url()` on this
 * model and there must never be one — see the migration and §6.
 */
class EmployeeDocument extends Model
{
    /**
     * What a document is for.
     *
     * `identity` is the one that matters: a PAN or Aadhaar scan is a different
     * class of data from a resume, and the page tones it differently so nobody
     * uploads one without noticing which pile it lands in.
     *
     * @var list<string>
     */
    public const KINDS = ['resume', 'identity', 'employment', 'other'];

    protected $fillable = [
        'reference', 'employee_id', 'name', 'kind', 'path', 'bytes', 'mime', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The shape the rail reads.
     *
     * `uploaded_by` comes out as 'self' or 'hr' rather than as a name, because
     * that is the only distinction the card draws and a name here would put a
     * colleague's into a list of somebody's identity documents.
     *
     * @return array<string, mixed>
     */
    public function toRecordArray(?int $viewerUserId = null): array
    {
        return [
            'id' => $this->reference,
            'name' => $this->name,
            'kind' => $this->kind,
            'bytes' => $this->bytes,
            'uploaded_by' => $this->uploaded_by !== null && $this->uploaded_by === $viewerUserId
                ? 'self'
                : 'hr',
            'uploaded_at' => $this->created_at,
        ];
    }
}
