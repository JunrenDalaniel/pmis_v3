<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    protected function canAccessBranch(User $authUser, ?int $branchId): bool
    {
        $allowed = $authUser->getAllowedBranchIds();
        if ($allowed === null) {
            return true;
        }

        return $branchId !== null && in_array($branchId, $allowed, true);
    }
    
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasPermissionTo('ViewAny:User');
    }

    public function view(User $authUser, User $user): bool
    {
        if (! $authUser->hasPermissionTo('View:User')) {
            return false;
        }

        return $authUser->id === $user->id
            || $this->canAccessBranch($authUser, $user->branch_office_id);
    }

    public function create(User $authUser): bool
    {
        return $authUser->hasPermissionTo('Create:User');
    }

    public function update(User $authUser, User $user): bool
    {
        if (! $authUser->hasPermissionTo('Update:User')) {
            return false;
        }

        // Prevent non-super admin from modifying super admin accounts
        if ($user->hasRole(['super_admin', 'Super Admin']) && ! $authUser->hasRole(['super_admin', 'Super Admin'])) {
            return false;
        }

        return $authUser->id === $user->id
            || $this->canAccessBranch($authUser, $user->branch_office_id);
    }

    public function delete(User $authUser, User $user): bool
    {
        if (! $authUser->hasPermissionTo('Delete:User')) {
            return false;
        }

        // Users cannot delete their own active account
        if ($authUser->id === $user->id) {
            return false;
        }

        // Protect Super Admin accounts from deletion
        if ($user->hasRole(['super_admin', 'Super Admin'])) {
            return false;
        }

        return $this->canAccessBranch($authUser, $user->branch_office_id);
    }

    public function deleteAny(User $authUser): bool
    {
        return $authUser->hasPermissionTo('DeleteAny:User');
    }

    public function restore(User $authUser, User $user): bool
    {
        return $authUser->hasPermissionTo('Restore:User')
            && $this->canAccessBranch($authUser, $user->branch_office_id);
    }

    public function forceDelete(User $authUser, User $user): bool
    {
        if (! $authUser->hasPermissionTo('ForceDelete:User')) {
            return false;
        }

        if ($authUser->id === $user->id || $user->hasRole(['super_admin', 'Super Admin'])) {
            return false;
        }

        return $this->canAccessBranch($authUser, $user->branch_office_id);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->hasPermissionTo('ForceDeleteAny:User');
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->hasPermissionTo('RestoreAny:User');
    }

    public function replicate(User $authUser, User $user): bool
    {
        return $authUser->hasPermissionTo('Replicate:User')
            && $this->canAccessBranch($authUser, $user->branch_office_id);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->hasPermissionTo('Reorder:User');
    }
}