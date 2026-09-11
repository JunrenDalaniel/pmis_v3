<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RodBranch;
use Illuminate\Auth\Access\HandlesAuthorization;

class RodBranchPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RodBranch');
    }

    public function view(AuthUser $authUser, RodBranch $rodBranch): bool
    {
        return $authUser->can('View:RodBranch');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RodBranch');
    }

    public function update(AuthUser $authUser, RodBranch $rodBranch): bool
    {
        return $authUser->can('Update:RodBranch');
    }

    public function delete(AuthUser $authUser, RodBranch $rodBranch): bool
    {
        return $authUser->can('Delete:RodBranch');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RodBranch');
    }

    public function restore(AuthUser $authUser, RodBranch $rodBranch): bool
    {
        return $authUser->can('Restore:RodBranch');
    }

    public function forceDelete(AuthUser $authUser, RodBranch $rodBranch): bool
    {
        return $authUser->can('ForceDelete:RodBranch');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RodBranch');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RodBranch');
    }

    public function replicate(AuthUser $authUser, RodBranch $rodBranch): bool
    {
        return $authUser->can('Replicate:RodBranch');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RodBranch');
    }

}