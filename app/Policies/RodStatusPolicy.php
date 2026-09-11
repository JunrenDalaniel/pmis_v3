<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RodStatus;
use Illuminate\Auth\Access\HandlesAuthorization;

class RodStatusPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RodStatus');
    }

    public function view(AuthUser $authUser, RodStatus $rodStatus): bool
    {
        return $authUser->can('View:RodStatus');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RodStatus');
    }

    public function update(AuthUser $authUser, RodStatus $rodStatus): bool
    {
        return $authUser->can('Update:RodStatus');
    }

    public function delete(AuthUser $authUser, RodStatus $rodStatus): bool
    {
        return $authUser->can('Delete:RodStatus');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RodStatus');
    }

    public function restore(AuthUser $authUser, RodStatus $rodStatus): bool
    {
        return $authUser->can('Restore:RodStatus');
    }

    public function forceDelete(AuthUser $authUser, RodStatus $rodStatus): bool
    {
        return $authUser->can('ForceDelete:RodStatus');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RodStatus');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RodStatus');
    }

    public function replicate(AuthUser $authUser, RodStatus $rodStatus): bool
    {
        return $authUser->can('Replicate:RodStatus');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RodStatus');
    }

}