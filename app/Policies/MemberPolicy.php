<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User as AuthUser;
use App\Models\Member;
use Illuminate\Auth\Access\HandlesAuthorization;

class MemberPolicy
{
    use HandlesAuthorization;

    protected function canAccessBranch(AuthUser $authUser, ?int $branchId): bool
    {
        $allowed = $authUser->getAllowedBranchIds();
        if ($allowed === null) {
            return true;
        }

        return $branchId !== null && in_array($branchId, $allowed, true);
    }
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('ViewAny:Member');
    }

    public function view(AuthUser $authUser, Member $member): bool
    {
        return $authUser->hasPermissionTo('View:Member')
            && $this->canAccessBranch($authUser, $member->branch_office_id);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('Create:Member');
    }

    public function update(AuthUser $authUser, Member $member): bool
    {
        return $authUser->hasPermissionTo('Update:Member')
            && $this->canAccessBranch($authUser, $member->branch_office_id);
    }

    public function delete(AuthUser $authUser, Member $member): bool
    {
        return $authUser->hasPermissionTo('Delete:Member')
            && $this->canAccessBranch($authUser, $member->branch_office_id);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('DeleteAny:Member');
    }

    public function restore(AuthUser $authUser, Member $member): bool
    {
        return $authUser->hasPermissionTo('Restore:Member')
            && $this->canAccessBranch($authUser, $member->branch_office_id);
    }

    public function forceDelete(AuthUser $authUser, Member $member): bool
    {
        return $authUser->hasPermissionTo('ForceDelete:Member')
            && $this->canAccessBranch($authUser, $member->branch_office_id);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('ForceDeleteAny:Member');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('RestoreAny:Member');
    }

    public function replicate(AuthUser $authUser, Member $member): bool
    {
        return $authUser->hasPermissionTo('Replicate:Member')
            && $this->canAccessBranch($authUser, $member->branch_office_id);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('Reorder:Member');
    }
}