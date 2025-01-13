<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Role;
use App\Models\Permission;
use Livewire\WithPagination;
use Livewire\Attributes\Rule;
class RoleManagement extends Component
{
    use WithPagination;

    #[Rule('required|min:3')]
    public string $name = '';

    #[Rule('required|unique:roles,slug')]
    public string $slug = '';

    public array $selectedPermissions = [];
    public bool $showCreateModal = false;
    public ?Role $editRole = null;

    public function create(): void
    {
        $this->validate();

        $role = Role::create([
            'name' => $this->name,
            'slug' => $this->slug,
        ]);

        $role->permissions()->sync($this->selectedPermissions);

        $this->reset();
        $this->showCreateModal = false;
        session()->flash('message', 'Role created successfully.');
    }

    public function delete(Role $role): void
    {
        if ($role->slug === 'super-admin') {
            session()->flash('error', 'Cannot delete super-admin role.');
            return;
        }

        $role->delete();
        session()->flash('message', 'Role deleted successfully.');
    }

    public function editRole(Role $role): void
    {
        $this->editRole = $role;
        $this->name = $role->name;
        $this->slug = $role->slug;
        $this->selectedPermissions = $role->permissions->pluck('id')->toArray();
        $this->showCreateModal = true;
    }
    #[Layout('layouts.app')] // Add this line
    public function render()
    {
        return view('livewire.role-management', [
            'roles' => Role::with('permissions')->paginate(10),
            'permissions' => Permission::all(),
        ]);
    }
}
