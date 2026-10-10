<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User as AuthUser;
use App\Models\AccountLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('ViewAny:AccountLog');
    }

    public function view(AuthUser $authUser, AccountLog $accountLog): bool
    {
        return $authUser->hasPermissionTo('View:AccountLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('Create:AccountLog');
    }

    public function update(AuthUser $authUser, AccountLog $accountLog): bool
    {
        return $authUser->hasPermissionTo('Update:AccountLog');
    }

    public function delete(AuthUser $authUser, AccountLog $accountLog): bool
    {
        return $authUser->hasPermissionTo('Delete:AccountLog');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('DeleteAny:AccountLog');
    }

    public function restore(AuthUser $authUser, AccountLog $accountLog): bool
    {
        return $authUser->hasPermissionTo('Restore:AccountLog');
    }

    public function forceDelete(AuthUser $authUser, AccountLog $accountLog): bool
    {
        return $authUser->hasPermissionTo('ForceDelete:AccountLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('ForceDeleteAny:AccountLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('RestoreAny:AccountLog');
    }

    public function replicate(AuthUser $authUser, AccountLog $accountLog): bool
    {
        return $authUser->hasPermissionTo('Replicate:AccountLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->hasPermissionTo('Reorder:AccountLog');
    }

}