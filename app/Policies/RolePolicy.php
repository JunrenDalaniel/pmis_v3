<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Role');
    }

    public function view(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('View:Role');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Role');
    }

    public function update(AuthUser $authUser, Role $role): bool
    {
        return $authUser->hasPermissionTo('Update:Role');
    }

    public function delete(AuthUser $authUser, Role $role): bool
    {
        // Never allow deleting the core super_admin role
        if ($role->name === 'super_admin' || $role->name === 'Super Admin' || $role->code === 'super_admin') {
            return false;
        }

        return $authUser->hasPermissionTo('Delete:Role');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('DeleteAny:Role');
    }

    public function restore(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Restore:Role');
    }

    public function forceDelete(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('ForceDelete:Role');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Role');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Role');
    }

    public function replicate(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Replicate:Role');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Role');
    }

}