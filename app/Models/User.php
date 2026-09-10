<?php

namespace App\Models;

use App\Support\Realm;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * An account (foundation spec §8).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THREE COLUMNS DECIDE EVERYTHING ELSE
 *
 * `account_type` is the realm (§3) — checked in middleware on every request
 * before any data is read.
 *
 * `staff_kind` grants the Employee base (§5). It is not a role, and it lives
 * on the account precisely so that no role edit can revoke it: an owner who
 * could remove it would take everyone's own attendance, leave and payslips
 * away at once.
 *
 * `status` decides whether the account may sign in at all (§4.2 step 4).
 *
 * Note what this class does NOT have: a `can()` of its own. Authorisation goes
 * through App\Support\Rbac\Rbac, one service answering for the whole
 * application, so there is a single place where the union and the Employee base
 * are resolved. A convenience method here would become a second implementation
 * of the same rules within a month.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * The bell's rows for this account.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THIS REPLACED Laravel's `Notifiable`, AND HAD TO
     *
     * The stock trait was on this model from the scaffold and never used —
     * nothing in the application calls `notify()`, and every message this
     * system sends goes out through a Mailable. What it did do was claim the
     * `notifications` table for `DatabaseNotification`, whose shape (a uuid
     * key, `type`, `notifiable_type`, a json `data` blob) is nothing like the
     * one §8 specifies and this module built.
     *
     * Left in place, `$user->notifications` would have quietly queried our
     * table through Laravel's model and returned rows with no `data` — a
     * collision that produces wrong answers rather than an error, and only on
     * the day somebody reaches for the relation they assume is there.
     *
     * Nothing is lost. Adding Laravel's notification system later means
     * choosing a table name then, which is a decision better made in the open
     * than inherited from a trait nobody put there on purpose.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->orderByDesc('id');
    }

    protected $fillable = [
        'user_id', 'name', 'email', 'password',
        'account_type', 'staff_kind', 'status', 'client_ref',
        'theme_preference',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['assigned_by', 'assigned_at']);
    }

    /**
     * Whether this account carries the Employee base — my attendance, my leave,
     * my payslips, my profile (§2.2).
     *
     * Staff of kind `employee` only. A Mentor is staff and has none of it
     * (§2.1); clients and the admin account are not staff at all.
     */
    public function hasEmployeeBase(): bool
    {
        return $this->account_type === Realm::STAFF && $this->staff_kind === 'employee';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * What the topbar calls this account's role.
     *
     * The roles it actually holds, not a stored label — a display string that
     * can disagree with the permissions is a support ticket waiting to happen.
     */
    public function getDisplayRoleAttribute(): string
    {
        return match ($this->account_type) {
            Realm::ADMIN => 'Owner',
            Realm::CLIENT => 'Client',
            default => $this->roles->pluck('role_name')->join(' · ') ?: 'No roles',
        };
    }
}
