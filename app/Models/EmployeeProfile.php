<?php

namespace App\Models;

use App\Support\Auth\EmailVerification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The fields a person owns about themselves.
 *
 * Everything here is ProfilePolicy::SELF. Nothing HR owns is on this model, and
 * that is the point of it being a separate table - a write that reaches this
 * row cannot touch a department, a designation or a name however it is shaped.
 */
class EmployeeProfile extends Model
{
    protected $fillable = [
        'employee_id',
        'phone', 'personal_email', 'current_address', 'permanent_address',
        'gender', 'marital_status', 'nationality',
        'languages', 'skills',
        'emergency_name', 'emergency_relationship', 'emergency_phone',
        'photo_path', 'notify_tasks', 'notify_tickets',
    ];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'skills' => 'array',
            'notify_tasks' => 'boolean',
            'notify_tickets' => 'boolean',
            'personal_email_verified_at' => 'datetime',
        ];
    }

    /**
     * A changed personal email signs in too, so it is unproven until its link
     * is followed (owner, 2026-10-28). See App\Support\Auth\EmailVerification.
     */
    protected static function booted(): void
    {
        static::updating(function (EmployeeProfile $profile) {
            if ($profile->isDirty('personal_email') && ! $profile->isDirty('personal_email_verified_at')) {
                $profile->personal_email_verified_at = null;
            }
        });

        static::updated(function (EmployeeProfile $profile) {
            $user = $profile->employee?->user;

            if ($profile->wasChanged('personal_email') && $profile->personal_email_verified_at === null && $user !== null) {
                EmailVerification::addressChanged($user, (string) $profile->personal_email);
            }
        });
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The defaults a person with no row reads as.
     *
     * Kept here rather than in the controller so "never opened the page" and
     * "opened it and cleared everything" render identically - the distinction
     * matters in the table (see the migration) and matters nowhere else.
     *
     * @return array<string, mixed>
     */
    public static function blank(): array
    {
        return [
            'phone' => null,
            'personal_email' => null,
            'current_address' => null,
            'permanent_address' => null,
            'gender' => null,
            'marital_status' => null,
            'nationality' => null,
            'languages' => [],
            'skills' => [],
            'emergency_name' => null,
            'emergency_relationship' => null,
            'emergency_phone' => null,
            'photo_path' => null,
            'notify_tasks' => true,
            'notify_tickets' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toRecordArray(): array
    {
        return [
            'phone' => $this->phone,
            'personal_email' => $this->personal_email,
            'current_address' => $this->current_address,
            'permanent_address' => $this->permanent_address,
            'gender' => $this->gender,
            'marital_status' => $this->marital_status,
            'nationality' => $this->nationality,
            'languages' => $this->languages ?? [],
            'skills' => $this->skills ?? [],
            'emergency_name' => $this->emergency_name,
            'emergency_relationship' => $this->emergency_relationship,
            'emergency_phone' => $this->emergency_phone,
            'photo_path' => $this->photo_path,
            'notify_tasks' => $this->notify_tasks,
            'notify_tickets' => $this->notify_tickets,
        ];
    }
}
