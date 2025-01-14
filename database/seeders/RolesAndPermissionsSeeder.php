<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Create Permissions
        $permissions = [
            'manage-users',
            'manage-roles',
            // Add more permissions as needed
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create Roles and assign Permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo('manage-users');
        $adminRole->givePermissionTo('manage-roles');

        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdminRole->givePermissionTo(Permission::all());

        // Create or find the super admin user
        $user = User::firstOrCreate(
            ['email' => 'root@root.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password')]
        );

        // Assign super-admin role to the user
        $user->assignRole($superAdminRole);
    }
}
