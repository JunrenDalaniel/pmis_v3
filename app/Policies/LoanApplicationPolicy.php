<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LoanApplication;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoanApplicationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LoanApplication');
    }

    public function view(AuthUser $authUser, LoanApplication $loanApplication): bool
    {
        return $authUser->can('View:LoanApplication');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LoanApplication');
    }

    public function update(AuthUser $authUser, LoanApplication $loanApplication): bool
    {
        return $authUser->can('Update:LoanApplication');
    }

    public function delete(AuthUser $authUser, LoanApplication $loanApplication): bool
    {
        return $authUser->can('Delete:LoanApplication');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LoanApplication');
    }

    public function restore(AuthUser $authUser, LoanApplication $loanApplication): bool
    {
        return $authUser->can('Restore:LoanApplication');
    }

    public function forceDelete(AuthUser $authUser, LoanApplication $loanApplication): bool
    {
        return $authUser->can('ForceDelete:LoanApplication');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LoanApplication');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LoanApplication');
    }

    public function replicate(AuthUser $authUser, LoanApplication $loanApplication): bool
    {
        return $authUser->can('Replicate:LoanApplication');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LoanApplication');
    }

}