<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
        'code',
        'scope',
        'description',
    ];

    public function assignedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
