<?php

namespace App\Livewire;

use App\Models\User;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\DynamicLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
// Add this import
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * Component for managing users in the application.
 *
 * Handles creation, editing, updating, and deletion of users.
 */
#[Layout('layouts.app', ['header' => 'User Management'])]
#[Title('User Management')]#[Lazy]
class UserManagement extends Component
{
    use WithPagination;

    /** @var string User's name */
    public string $name = '';

    /** @var string User's email */
    public string $email = '';

    /** @var string User's password */
    public string $password = '';

    /** @var string Selected role ID */
    public $selectedRole = '';

    /** @var bool Whether to show the create/edit modal */
    public bool $showCreateModal = false;

    /** @var User|null The user being edited */
    public ?User $editUser = null;

    /** @var bool Whether the form is in editing mode */
    public bool $isEditing = false;

    protected $logger;

    public function boot(DynamicLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Validation rules for user creation and updates.
     */
    protected function rules(): array
    {
        return [
            'name' => 'required|min:3',
            'email' => $this->isEditing
                ? 'required|email|unique:users,email,'.$this->editUser->id
                : 'required|email|unique:users,email',
            'password' => $this->isEditing ? 'nullable|min:8' : 'required|min:8',
            'selectedRole' => 'required',
        ];
    }

    /**
     * Opens the modal for editing a user.
     */
    #[On('edit-user')]
    public function editUser(int $userId): void
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

    /**
     * Creates a new user with the specified details and role.
     */
    #[On('create-user')]
    public function createUser(): void
    {
        $this->validate();
        try {
            $userRepository = new UserRepository(new RoleRepository);
            $user = $userRepository->createUser(
                $this->name,
                $this->email,
                $this->password,
                $this->selectedRole
            );
            session()->flash('message', 'User created successfully.');
            $this->logger->log('info', 'User created successfully', ['user_id' => $user->id, 'email' => $user->email]);
            $this->showCreateModal = false;
            $this->resetForm();
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error updating user: '.$e->getMessage(), ['email' => $this->email]);
            session()->flash('error', 'Error creating user: '.$e->getMessage());
        }
    }

    /**
     * Updates an existing user's details.
     */
    #[On('update-user')]
    public function updateUser(): void
    {
        $this->validate();
        try {
            $userRepository = new UserRepository(new RoleRepository);
            $userRepository->updateUser(
                $this->editUser,
                $this->name,
                $this->email,
                $this->password,
                $this->selectedRole
            );
            session()->flash('message', 'User updated successfully.');
            $this->logger->log('info', 'User updated successfully', ['user_id' => $this->editUser->id, 'email' => $this->email]);
            $this->showCreateModal = false;
            $this->resetForm();
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error updating user: '.$e->getMessage(), ['user_id' => $this->editUser?->id]);
            session()->flash('error', 'Error updating user: '.$e->getMessage());
        }
    }

    /**
     * Deletes a user by their ID.
     */
    #[On('delete-user')]
    public function deleteUser(int $userId): void
    {
        try {
            $user = User::findOrFail($userId);
            $userRepository = new UserRepository(new RoleRepository);
            $userRepository->deleteUser($user);
            $this->logger->log('info', 'User deleted successfully', ['user_id' => $userId, 'email' => $user->email]);
            session()->flash('message', 'User deleted successfully.');
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error deleting user: '.$e->getMessage(), ['user_id' => $userId]);
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Resets the form fields and validation errors.
     */
    public function resetForm(): void
    {
        $this->reset(['name', 'email', 'password', 'selectedRole', 'showCreateModal', 'editUser', 'isEditing']);
        $this->resetValidation();
    }

    /**
     * Renders the Livewire component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.user-management', [
            'users' => User::with('roles')->paginate(10),
            'roles' => Role::all(),
        ]);
    }
}
