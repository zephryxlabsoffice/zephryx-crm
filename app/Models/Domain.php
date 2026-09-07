<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A domain rank is measured in (foundation spec §2.5): people, finance, work,
 * support, system.
 *
 * Rank exists only to answer "may I act on this person" and to route
 * approvals. It never grants a permission.
 */
class Domain extends Model
{
    public const PEOPLE = 'people';
    public const FINANCE = 'finance';
    public const WORK = 'work';
    public const SUPPORT = 'support';
    public const SYSTEM = 'system';

    public $timestamps = false;

    protected $fillable = ['domain_key', 'domain_name'];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_domain_rank')->withPivot('rank');
    }
}
