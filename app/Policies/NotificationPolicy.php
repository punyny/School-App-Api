<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher', 'student', 'parent'], true);
    }

    public function view(User $user, Notification $notification): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ((int) $notification->user_id === (int) $user->id) {
            return true;
        }

        return $user->role === 'admin'
            && $user->school_id
            && (int) ($notification->user?->school_id) === (int) $user->school_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin'], true);
    }

    public function update(User $user, Notification $notification): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        return $user->role === 'admin'
            && $user->school_id
            && (int) ($notification->user?->school_id) === (int) $user->school_id;
    }

    public function updateReadStatus(User $user, Notification $notification): bool
    {
        return (int) $notification->user_id === (int) $user->id;
    }

    public function delete(User $user, Notification $notification): bool
    {
        if ((int) $notification->user_id === (int) $user->id && $user->role !== 'teacher') {
            return true;
        }

        if ($user->role === 'super-admin') {
            return true;
        }

        return $user->role === 'admin'
            && $user->school_id
            && (int) ($notification->user?->school_id) === (int) $user->school_id;
    }
}
