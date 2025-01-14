<?php
namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Repositories\RoleRepository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class RoleManagement extends Component
{
    use WithPagination;

    public string $name = '';
    public array $selectedPermissions = [];
    public bool $showCreateModal = false;
    public ?int $editRoleId = null;

    public function mount()
    {
        $this->roleRepository = new RoleRepository();
    }

    protected function rules()
    {
        $uniqueRule = $this->editRoleId
            ? "unique:roles,name,{$this->editRoleId}"
            : 'unique:roles,name';

        return [
            'name' => ['required', 'string', 'max:255', $uniqueRule],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['exists:permissions,id']
        ];
    }

    public function editRole(int $roleId)
    {
        $role = Role::with('permissions')->findOrFail($roleId);
        $this->editRoleId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('id')->toArray();
        $this->showCreateModal = true;
    }

    public function create()
    {
        $validatedData = $this->validate();

        try {
            $roleRepository = new RoleRepository();
            $roleRepository->createRole(
                $this->name,
                $this->selectedPermissions
            );

            session()->flash('message', __('Role created successfully.'));
            $this->showCreateModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Error creating role: ' . $e->getMessage());
        }
        $this->resetForm();
    }

    public function update()
    {
        $validatedData = $this->validate();

        try {
            $role = Role::findOrFail($this->editRoleId);
            $roleRepository = new RoleRepository();
            $roleRepository->updateRole(
                $role,
                $this->name,
                $this->selectedPermissions
            );

            session()->flash('message', __('Role updated successfully.'));
            $this->showCreateModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating role: ' . $e->getMessage());
        }
        $this->resetForm();
    }

    public function delete(int $roleId)
    {
        try {
            $role = Role::findOrFail($roleId);
            $roleRepository = new RoleRepository();
            $roleRepository->deleteRole($role);
            session()->flash('message', __('Role deleted successfully.'));
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function resetForm()
    {
        $this->name = '';
        $this->selectedPermissions = [];
        $this->editRoleId = null;
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.role-management', [
            'roles' => Role::with('permissions')->paginate(10),
            'permissions' => Permission::all(),
        ]);
    }
}
