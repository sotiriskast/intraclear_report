<?php

namespace Tests\Feature;

use App\Livewire\RoleManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Permission::create(['name' => 'manage-users']);
    }

    public function test_create_role()
    {
        $permission = Permission::create([
            'name' => 'test-permission-' . uniqid()
        ]);

        // Test the component
        $component = Livewire::test(RoleManagement::class)
            ->set('name', 'Manager')
            ->set('selectedPermissions', [$permission->id])
            ->call('create');

        // Assert database state
        $this->assertDatabaseHas('roles', [
            'name' => 'Manager'
        ]);

        // Assert component state
        $component->assertSet('showCreateModal', false);

        // Assert role-permission relationship
        $role = Role::where('name', 'Manager')->first();
        $this->assertTrue($role->hasPermissionTo($permission->name));
    }

    public function test_update_role()
    {
        $role = Role::create(['name' => 'Editor']);

        Livewire::test('role-management')
            ->set('editRoleId', $role->id)
            ->set('name', 'Updated Editor')
            ->call('update')
            ->assertSee(__('Role updated successfully.'));

        $this->assertDatabaseHas('roles', ['name' => 'Updated Editor']);
    }

    public function test_delete_role()
    {
        $role = Role::create(['name' => 'Temporary Role']);

        Livewire::test('role-management')
            ->call('delete', $role->id)
            ->assertSee(__('Role deleted successfully.'));

        $this->assertDatabaseMissing('roles', ['name' => 'Temporary Role']);
    }
}
