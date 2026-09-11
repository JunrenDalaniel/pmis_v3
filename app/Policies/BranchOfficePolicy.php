<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BranchOffice;
use Illuminate\Auth\Access\HandlesAuthorization;

class BranchOfficePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BranchOffice');
    }

    public function view(AuthUser $authUser, BranchOffice $branchOffice): bool
    {
        return $authUser->can('View:BranchOffice');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BranchOffice');
    }

    public function update(AuthUser $authUser, BranchOffice $branchOffice): bool
    {
        return $authUser->can('Update:BranchOffice');
    }

    public function delete(AuthUser $authUser, BranchOffice $branchOffice): bool
    {
        return $authUser->can('Delete:BranchOffice');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BranchOffice');
    }

    public function restore(AuthUser $authUser, BranchOffice $branchOffice): bool
    {
        return $authUser->can('Restore:BranchOffice');
    }

    public function forceDelete(AuthUser $authUser, BranchOffice $branchOffice): bool
    {
        return $authUser->can('ForceDelete:BranchOffice');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BranchOffice');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BranchOffice');
    }

    public function replicate(AuthUser $authUser, BranchOffice $branchOffice): bool
    {
        return $authUser->can('Replicate:BranchOffice');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BranchOffice');
    }

}