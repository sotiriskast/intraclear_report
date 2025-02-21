<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            'manage-users',
            'manage-roles',
            'manage-merchants',
            'manage-merchants-fees',
            'manage-fees',
            'manage-settlements',
            'manage-merchants-api-keys'

            // Add more granular permissions
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create Admin Role
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo([
            'manage-fees',
            'manage-merchants',
            'manage-settlements',
            'manage-merchants-fees'
        ]);

        // Create Super Admin Role
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdminRole->givePermissionTo(Permission::all());

        $user = User::firstOrCreate(
            ['email' => 'root@root.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password')]
        );

        $user->assignRole($superAdminRole);
    }
}
