<?php

namespace App\Console\Commands;

use App\Services\DynamicLogger;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AddPermission extends Command
{
    protected $signature = 'permission:add {name} {--r|roles=*} {--A|all-roles}';
    protected $description = 'Add a new permission and assign it to roles';

    public function __construct(
        private DynamicLogger $logger,
    )
    {
        parent::__construct();
    }

    public function handle()
    {
        $permissionName = $this->argument('name');
        $roles = $this->option('roles');
        $allRoles = $this->option('all-roles');

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permission
        $permission = Permission::firstOrCreate(['name' => $permissionName]);


        if ($permission->wasRecentlyCreated) {
            $this->info("Permission '{$permissionName}' created successfully.");
            $this->logger->log('info', 'Permission created' . $permissionName . ' to ' . $permission->name);
        } else {
            $this->warn("Permission '{$permissionName}' already exists.");
            $this->logger->log('warning', 'Permission already exists ' . $permissionName . ' to ' . $permission->name);
        }

        // Add to super-admin role by default
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissionName);
            $this->line("Added to 'super-admin' role.");
            $this->logger->log('info', 'Adding permission ' . $permissionName . ' to ' . $permission->name);
        }

        // Add to specified roles
        if (!empty($roles)) {
            foreach ($roles as $roleName) {
                $role = Role::where('name', $roleName)->first();
                if ($role) {
                    $role->givePermissionTo($permissionName);
                    $this->line("Added to '{$roleName}' role.");
                    $this->logger->log('info', 'Give permission ' . $permissionName . ' to ' . $roleName);
                } else {
                    $this->error("Role '{$roleName}' not found.");
                    $this->logger->log('error', 'Role ' . $roleName . ' not found');

                }
            }
        }

        // Add to all roles if requested
        if ($allRoles) {
            $allRolesModels = Role::all();
            foreach ($allRolesModels as $role) {
                $role->givePermissionTo($permissionName);
                $this->line("Added to '{$role->name}' role.");
            }
            $this->logger->log('info', 'All perrmissions given to all roles.');
        }
        return 0;
    }
}
