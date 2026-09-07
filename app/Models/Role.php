<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A capability pack (foundation spec §2.4).
 *
 * Roles layer on top of the Employee base and stack as a union — nothing is
 * ever lost by gaining one. That is why there is no precedence or priority
 * column here: with only additive grants, resolution needs no ordering.
 */
class Role extends Model
{
    protected $fillable = ['role_key', 'role_name', 'description', 'parent_role_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['assigned_by', 'assigned_at']);
    }

    /**
     * @return BelongsToMany<Domain, $this>
     */
    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'role_domain_rank')->withPivot('rank');
    }
}
