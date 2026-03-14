<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin'], true);
    }

    public function view(User $user, User $targetUser): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($user->role !== 'admin' || ! $user->school_id) {
            return false;
        }

        return $targetUser->role !== 'super-admin'
            && (int) $targetUser->school_id === (int) $user->school_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $targetUser): bool
    {
        return $this->view($user, $targetUser);
    }

    public function delete(User $user, User $targetUser): bool
    {
        if ((int) $user->id === (int) $targetUser->id) {
            return false;
        }

        return $this->view($user, $targetUser);
    }
}
