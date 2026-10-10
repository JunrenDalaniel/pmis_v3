<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User as AuthUser;
use App\Models\LoanApplication;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoanApplicationPolicy
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
        return $authUser->hasPermissionTo('ViewAny:LoanApplication');
    }

    public function view(AuthUser $authUser, LoanApplication $loanApplication): bool
    {
        return $authUser->hasPermissionTo('View:LoanApplication')
            && $this->canAccessBranch($authUser, $loanApplication->branch_office_id);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('Create:LoanApplication');
    }

    public function update(AuthUser $authUser, LoanApplication $loanApplication): bool
    {
        return $authUser->hasPermissionTo('Update:LoanApplication')
            && $this->canAccessBranch($authUser, $loanApplication->branch_office_id);
    }

    public function delete(AuthUser $authUser, LoanApplication $loanApplication): bool
    {
        return $authUser->hasPermissionTo('Delete:LoanApplication')
            && $this->canAccessBranch($authUser, $loanApplication->branch_office_id);
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('DeleteAny:LoanApplication');
    }

    public function restore(AuthUser $authUser, LoanApplication $loanApplication): bool
    {
        return $authUser->hasPermissionTo('Restore:LoanApplication')
            && $this->canAccessBranch($authUser, $loanApplication->branch_office_id);
    }

    public function forceDelete(AuthUser $authUser, LoanApplication $loanApplication): bool
    {
        return $authUser->hasPermissionTo('ForceDelete:LoanApplication')
            && $this->canAccessBranch($authUser, $loanApplication->branch_office_id);
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('ForceDeleteAny:LoanApplication');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('RestoreAny:LoanApplication');
    }

    public function replicate(AuthUser $authUser, LoanApplication $loanApplication): bool
    {
        return $authUser->hasPermissionTo('Replicate:LoanApplication')
            && $this->canAccessBranch($authUser, $loanApplication->branch_office_id);
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('Reorder:LoanApplication');
    }
}