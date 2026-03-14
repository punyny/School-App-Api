<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'super-admin';
    }

    public function view(User $user, School $school): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, School $school): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, School $school): bool
    {
        return $this->viewAny($user);
    }
}
