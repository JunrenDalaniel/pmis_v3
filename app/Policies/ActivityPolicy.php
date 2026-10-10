<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User as AuthUser;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Auth\Access\HandlesAuthorization;

class ActivityPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('ViewAny:Activity');
    }

    public function view(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->hasPermissionTo('View:Activity');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('Create:Activity');
    }

    public function update(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->hasPermissionTo('Update:Activity');
    }

    public function delete(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->hasPermissionTo('Delete:Activity');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('DeleteAny:Activity');
    }

    public function restore(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->hasPermissionTo('Restore:Activity');
    }

    public function forceDelete(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->hasPermissionTo('ForceDelete:Activity');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('ForceDeleteAny:Activity');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('RestoreAny:Activity');
    }

    public function replicate(AuthUser $authUser, Activity $activity): bool
    {
        return $authUser->hasPermissionTo('Replicate:Activity');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('Reorder:Activity');
    }

}