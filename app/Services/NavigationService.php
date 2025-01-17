<?php

namespace App\Services;

use App\Models\User;

class NavigationService
{
    // This will generate dynamic navigation based on roles and permissions
    public function getNavigation(User $user)
    {
        return [
            'dashboard' => [
                'route' => 'dashboard',
                'label' => 'Dashboard',
                'icon' => 'dashboard',
                'permission' => null, // Always visible
            ],
            'users' => [
                'label' => 'Users',
                'icon' => 'users',
                'children' => [
                    'user_management' => [
                        'route' => 'admin.users',
                        'label' => 'User Management',
                        'permission' => 'manage-users', // Permission check will be dynamic
                    ],
                    'roles' => [
                        'route' => 'admin.roles',
                        'label' => 'Roles',
                        'permission' => 'manage-roles', // Permission check will be dynamic
                    ],
                ],
            ],
            'settings' => [
                'route' => 'profile.show',
                'label' => 'Settings',
                'icon' => 'settings',
                'permission' => null,
            ],

            // Additional dynamic items can be added here
        ];
    }

    // This method filters the navigation items based on the user’s permissions
    public function filterNavigation(User $user, $navigation = null)
    {
        // If no navigation structure is provided, we generate one
        $navigation = $navigation ?? $this->getNavigation($user);

        foreach ($navigation as $key => &$item) {
            // Check top-level item permission dynamically
            if (isset($item['permission']) && $item['permission'] !== null) {
                $item['visible'] = $this->userHasPermission($user, $item['permission']);
            } else {
                $item['visible'] = true;
            }

            // Handle children navigation items
            if (isset($item['children'])) {
                foreach ($item['children'] as $childKey => &$child) {
                    // Check child permission dynamically
                    if (isset($child['permission'])) {
                        $child['visible'] = $this->userHasPermission($user, $child['permission']);
                    } else {
                        $child['visible'] = true;
                    }
                }

                // Remove invisible children
                $item['children'] = array_filter($item['children'], function ($child) {
                    return $child['visible'] ?? false;
                });

                // If no visible children, hide the parent
                if (empty($item['children'])) {
                    $item['visible'] = false;
                }
            }
        }

        // Remove invisible top-level items
        return array_filter($navigation, function ($item) {
            return $item['visible'] ?? false;
        });
    }

    // Helper method to check if the user has a specific permission
    private function userHasPermission(User $user, string $permission)
    {
        // Dynamically check if user has the permission using Spatie's hasPermissionTo
        return $user->hasPermissionTo($permission);
    }

    // This helper method can be used to check if the user has any of the given roles
    private function userHasRole(User $user, string $role)
    {
        // Dynamically check if user has the role using Spatie's hasRole
        return $user->hasRole($role);
    }
}
