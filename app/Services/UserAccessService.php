<?php
namespace App\Services;

use App\Models\User;

class UserAccessService
{
    public function hasRole(User $user, string $roleName): bool
    {
        return $user->hasRole($roleName); // Use Spatie’s hasRole
    }

    public function hasAnyRole(User $user, array $roleNames): bool
    {
        return $user->hasAnyRole($roleNames); // Use Spatie’s hasAnyRole
    }

    public function hasPermission(User $user, string $permissionName): bool
    {
        return $user->hasPermissionTo($permissionName); // Use Spatie’s hasPermissionTo
    }
}
