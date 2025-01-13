<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $superAdmin = Role::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
        ]);

        $admin = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        // Create permissions
        $manageUsers = Permission::create([
            'name' => 'Manage Users',
            'slug' => 'manage-users',
        ]);

        $manageRoles = Permission::create([
            'name' => 'Manage Roles',
            'slug' => 'manage-roles',
        ]);

        // Assign permissions to roles
        $superAdmin->permissions()->attach([$manageUsers->id, $manageRoles->id]);
        $admin->permissions()->attach([$manageUsers->id]);

        // Create super admin user
        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password123'),
        ]);

        $user->roles()->attach($superAdmin);
    }
}
