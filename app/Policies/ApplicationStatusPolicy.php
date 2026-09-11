<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ApplicationStatus;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApplicationStatusPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ApplicationStatus');
    }

    public function view(AuthUser $authUser, ApplicationStatus $applicationStatus): bool
    {
        return $authUser->can('View:ApplicationStatus');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ApplicationStatus');
    }

    public function update(AuthUser $authUser, ApplicationStatus $applicationStatus): bool
    {
        return $authUser->can('Update:ApplicationStatus');
    }

    public function delete(AuthUser $authUser, ApplicationStatus $applicationStatus): bool
    {
        return $authUser->can('Delete:ApplicationStatus');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ApplicationStatus');
    }

    public function restore(AuthUser $authUser, ApplicationStatus $applicationStatus): bool
    {
        return $authUser->can('Restore:ApplicationStatus');
    }

    public function forceDelete(AuthUser $authUser, ApplicationStatus $applicationStatus): bool
    {
        return $authUser->can('ForceDelete:ApplicationStatus');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ApplicationStatus');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ApplicationStatus');
    }

    public function replicate(AuthUser $authUser, ApplicationStatus $applicationStatus): bool
    {
        return $authUser->can('Replicate:ApplicationStatus');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ApplicationStatus');
    }

}