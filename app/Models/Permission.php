<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $module
 * @property string|null $description
 */
class Permission extends Model
{
    use CentralConnection;

    protected $fillable = [
        'name',
        'slug',
        'module',
        'description',
    ];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
