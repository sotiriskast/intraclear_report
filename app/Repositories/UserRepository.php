<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserRepository
{
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

    private function isLastSuperAdmin(User $user): bool
    {
        if ($user->hasRole('super-admin')) {  // Changed from checking slug to using hasRole
            return User::role('super-admin')->count() <= 1;  // Changed to use role name directly
        }

        return false;
    }
}
