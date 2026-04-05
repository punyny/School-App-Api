<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin'], true);
    }

    public function view(User $user, School $school): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        return $user->role === 'admin' && (int) ($user->school_id ?? 0) === (int) $school->id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'super-admin';
    }

    public function update(User $user, School $school): bool
    {
        return $this->view($user, $school);
    }

    public function delete(User $user, School $school): bool
    {
        return $user->role === 'super-admin';
    }
}
