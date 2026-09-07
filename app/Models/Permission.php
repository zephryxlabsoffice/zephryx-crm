<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A discrete capability, keyed `module.action` (foundation spec §5).
 *
 * Keys rather than levels, so union resolution across stacked roles is
 * inherent and needs no precedence logic: holding `leave.view` and
 * `leave.approve` is simply holding both.
 */
class Permission extends Model
{
    protected $fillable = ['permission_key', 'permission_name', 'module', 'description', 'is_sensitive'];

    protected function casts(): array
    {
        return ['is_sensitive' => 'boolean'];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
