<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $level
 * @property string|null $description
 * @property bool $is_system
 */
class Role extends Model
{
    use CentralConnection;

    protected $fillable = [
        'name',
        'slug',
        'level',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }
}
