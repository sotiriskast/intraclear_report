<?php
namespace App\Livewire;

use App\Models\User;
use App\Repositories\RoleRepository;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On; // Add this import
use App\Repositories\UserRepository;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class UserManagement extends Component
{
    use WithPagination;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public $selectedRole = '';
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
            'selectedRole' => 'required'
        ];
    }

    #[On('edit-user')] // Add this attribute
    public function editUser($userId)
    {
        $this->editUser = User::with('roles')->find($userId);
        if ($this->editUser) {
            $this->name = $this->editUser->name;
            $this->email = $this->editUser->email;
            $this->selectedRole = $this->editUser->roles->first()?->id ?? '';
            $this->showCreateModal = true;
            $this->isEditing = true;
        }
    }

    #[On('create-user')] // Add this attribute
    public function createUser()
    {
        $this->validate();

        try {
            $userRepository = new UserRepository(new RoleRepository());
            $userRepository->createUser(
                $this->name,
                $this->email,
                $this->password,
                $this->selectedRole
            );

            session()->flash('message', 'User created successfully.');
            $this->showCreateModal = false;
            $this->resetForm();
        } catch (\Exception $e) {
            session()->flash('error', 'Error creating user: ' . $e->getMessage());
        }
    }

    #[On('update-user')] // Add this attribute
    public function updateUser()
    {
        $this->validate();

        try {
            $userRepository = new UserRepository(new RoleRepository());
            $userRepository->updateUser(
                $this->editUser,
                $this->name,
                $this->email,
                $this->password,
                $this->selectedRole
            );

            session()->flash('message', 'User updated successfully.');
            $this->showCreateModal = false;
            $this->resetForm();
        } catch (\Exception $e) {
            session()->flash('error', 'Error updating user: ' . $e->getMessage());
        }
    }

    #[On('delete-user')] // Add this attribute
    public function deleteUser($userId)
    {
        try {
            $user = User::findOrFail($userId);
            $userRepository = new UserRepository(new RoleRepository());
            $userRepository->deleteUser($user);
            session()->flash('message', 'User deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function resetForm()
    {
        $this->reset(['name', 'email', 'password', 'selectedRole', 'showCreateModal', 'editUser', 'isEditing']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.user-management', [
            'users' => User::with('roles')->paginate(10),
            'roles' => Role::all(),
        ]);
    }
}
