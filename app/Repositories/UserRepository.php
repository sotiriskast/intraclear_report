<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
/**
 * Repository for managing users
 *
 * This repository handles:
 * - User creation and updates
 * - User deletion with safety checks
 * - Role assignment and management
 * - Password hashing
 * - Super-admin protection
 */
class UserRepository
{
    /**
     * Create a new user with role
     *
     * @param string $name User's name
     * @param string $email User's email
     * @param string $password User's password (will be hashed)
     * @param int $role Role ID to assign
     * @return User Newly created user
     */
    public function createUser(string $name, string $email, string $password, int $role): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        // Assign role
        $role = Role::findById($role);
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }
    /**
     * Update an existing user
     *
     * @param User $user User to update
     * @param string $name New name
     * @param string $email New email
     * @param string|null $password New password (optional)
     * @param int $role New role ID
     * @return User Updated user
     */
    public function updateUser(User $user, string $name, string $email, ?string $password, int $role): User
    {
        $userData = [
            'name' => $name,
            'email' => $email,
        ];

        if ($password) {
            $userData['password'] = Hash::make($password);
        }

        $user->update($userData);

        // Sync role
        $role = Role::findById($role);
        if ($role) {
            $user->syncRoles([$role]);
        }

        return $user;
    }
    /**
     * Delete a user with safety checks
     *
     * @param User $user User to delete
     * @return bool True if deleted successfully
     * @throws ValidationException If attempting to delete own account or last super-admin
     */
    public function deleteUser(User $user): bool
    {
        if ($user->id === auth()->id()) {
            throw ValidationException::withMessages(['error' => 'You cannot delete your own account.']);
        }

        if ($this->isLastSuperAdmin($user)) {
            throw ValidationException::withMessages(['error' => 'Cannot delete the last super admin account.']);
        }

        return $user->delete();
    }
    /**
     * Check if user is the last super-admin
     *
     * @param User $user User to check
     * @return bool True if user is the last super-admin
     */
    private function isLastSuperAdmin(User $user): bool
    {
        if ($user->hasRole('super-admin')) {  // Changed from checking slug to using hasRole
            return User::role('super-admin')->count() <= 1;  // Changed to use role name directly
        }

        return false;
    }
}
