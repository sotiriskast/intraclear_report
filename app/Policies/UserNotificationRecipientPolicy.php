<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserNotificationRecipient;
use Illuminate\Auth\Access\Response;

class UserNotificationRecipientPolicy
{
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return null;
    }
    public function viewAny(User $user): bool
    {
        return true; // Users can view their recipients list
    }

    public function view(User $user, UserNotificationRecipient $recipient): bool
    {
        return $user->hasRole('super-admin') || $user->id === $recipient->user_id;
    }

    public function create(User $user): bool
    {
        return true; // Any authenticated user can create recipients
    }

    public function delete(User $user, UserNotificationRecipient $recipient): bool
    {
        return $user->hasRole('super-admin') || $user->id === $recipient->user_id;
    }

    public function update(User $user, UserNotificationRecipient $recipient): bool
    {
        return $user->hasRole('super-admin') || $user->id === $recipient->user_id;
    }
    public function toggleActive(User $user, UserNotificationRecipient $recipient): bool
    {
        return $user->hasRole('super-admin') || $user->id === $recipient->user_id;
    }
}
