<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\Role;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Rule;
use Illuminate\Support\Facades\Hash;

class UserManagement extends Component
{
    use WithPagination;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public $selectedRole = ''; // Changed from array to single value
    public bool $showCreateModal = false;
    public ?User $editUser = null;
    public bool $isEditing = false;

    protected function rules()
    {
        return [
            'name' => 'required|min:3',
            'email' => $this->isEditing
                ? 'required|email|unique:users,email,' . $this->editUser->id
                : 'required|email|unique:users,email',
            'password' => $this->isEditing ? 'nullable|min:8' : 'required|min:8',
            'selectedRole' => 'required' // Changed validation rule
        ];
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.user-management', [
            'users' => User::with('roles')->paginate(10),
            'roles' => Role::all(),
        ]);
    }

    #[On('createUser')]
    public function create()
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        $user->roles()->sync([$this->selectedRole]); // Changed to sync single role

        $this->resetForm();
        session()->flash('message', 'User created successfully.');
    }

    #[On('editUser')]
    public function editUser($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        $this->isEditing = true;
        $this->editUser = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->selectedRole = $user->roles->first()?->id ?? ''; // Get first role ID
        $this->showCreateModal = true;
    }

    #[On('updateUser')]
    public function update()
    {
        $this->validate([
            'name' => 'required|min:3',
            'email' => 'required|email|unique:users,email,' . $this->editUser->id,
            'selectedRole' => 'required'
        ]);

        // Check if updating would remove the last super admin
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        if ($superAdminRole
            && $this->editUser->roles->contains($superAdminRole)
            && $this->selectedRole != $superAdminRole->id
            && $this->isLastSuperAdmin($this->editUser)) {
            session()->flash('error', 'Cannot remove super admin role from the last super admin user.');
            return;
        }

        $this->editUser->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        $this->editUser->roles()->sync([$this->selectedRole]); // Sync single role

        $this->resetForm();
        session()->flash('message', 'User updated successfully.');
    }

    #[On('deleteUser')]
    public function delete($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return;
        }

        if ($user->id === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }

        if ($this->isLastSuperAdmin($user)) {
            session()->flash('error', 'Cannot delete the last super admin account.');
            return;
        }

        $user->delete();
        session()->flash('message', 'User deleted successfully.');
    }

    protected function isLastSuperAdmin(User $user): bool
    {
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        if (!$superAdminRole) {
            return false;
        }

        if ($user->roles->contains($superAdminRole)) {
            $superAdminCount = User::whereHas('roles', function($query) use ($superAdminRole) {
                $query->where('roles.id', $superAdminRole->id);
            })->count();

            return $superAdminCount <= 1;
        }

        return false;
    }

    public function resetForm()
    {
        $this->reset(['name', 'email', 'password', 'selectedRole', 'showCreateModal', 'editUser', 'isEditing']);
        $this->resetValidation();
    }
}
