<?php

namespace App\Repositories;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleRepository
{
    public function createRole(string $name, array $permissionIds = []): Role
    {
        // Create the role
        $role = Role::create([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        // Get Permission objects from IDs and sync them
        if (! empty($permissionIds)) {
            $permissions = Permission::whereIn('id', $permissionIds)->get();
            $role->syncPermissions($permissions);
        }

        return $role;
    }

    public function updateRole(Role $role, string $name, array $permissionIds = []): Role
    {
        $role->update([
            'name' => $name,
        ]);

        // Get Permission objects from IDs and sync them
        $permissions = Permission::whereIn('id', $permissionIds)->get();
        $role->syncPermissions($permissions);

        return $role;
    }

    public function deleteRole(Role $role): bool
    {
        if ($role->name === 'super-admin') {
            throw new \Exception('Cannot delete super-admin role.');
        }

        return $role->delete();
    }
}
