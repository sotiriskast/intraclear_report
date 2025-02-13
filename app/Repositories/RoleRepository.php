<?php

namespace App\Repositories;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Repository for managing user roles
 *
 * This repository handles:
 * - Creating new roles
 * - Updating existing roles
 * - Deleting roles
 * - Managing role permissions
 * - Role validation and protection of super-admin role
 */
class RoleRepository
{
    /**
     * Create a new role with optional permissions
     *
     * @param string $name Name of the role
     * @param array $permissionIds Array of permission IDs to assign
     * @return Role Newly created role
     */
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
    /**
     * Update an existing role and its permissions
     *
     * @param Role $role Role to update
     * @param string $name New name for the role
     * @param array $permissionIds Array of permission IDs to assign
     * @return Role Updated role
     */
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
    /**
     * Delete a role if it's not protected
     *
     * @param Role $role Role to delete
     * @return bool True if deleted successfully
     * @throws \Exception If attempting to delete super-admin role
     */
    public function deleteRole(Role $role): bool
    {
        if ($role->name === 'super-admin') {
            throw new \Exception('Cannot delete super-admin role.');
        }

        return $role->delete();
    }
}
