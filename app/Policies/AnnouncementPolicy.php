<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher', 'student', 'parent'], true);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($user->role === 'admin') {
            return (int) $user->school_id === (int) $announcement->school_id;
        }

        $role = $this->normalizeRole((string) $user->role);
        if (! in_array($role, ['teacher', 'student', 'parent'], true)) {
            return false;
        }

        if ($user->school_id && (int) $user->school_id !== (int) $announcement->school_id) {
            return false;
        }

        if ($announcement->target_user_id) {
            return (int) $announcement->target_user_id === (int) $user->id;
        }

        if ($announcement->target_role) {
            return $this->normalizeRole((string) $announcement->target_role) === $role;
        }

        if ($announcement->class_id) {
            return match ($role) {
                'teacher' => $user->teachingClasses()->where('classes.id', $announcement->class_id)->exists(),
                'student' => (int) ($user->studentProfile?->class_id) === (int) $announcement->class_id,
                'parent' => $user->children()->where('students.class_id', $announcement->class_id)->exists(),
                default => false,
            };
        }

        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher'], true);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($user->role !== 'admin') {
            return false;
        }

        if ($user->role === 'teacher') {
            if ((int) ($user->school_id ?? 0) !== (int) $announcement->school_id) {
                return false;
            }

            if ((int) ($announcement->posted_by ?? 0) !== (int) $user->id) {
                return false;
            }

            $classId = (int) ($announcement->class_id ?? 0);
            if ($classId <= 0) {
                return false;
            }

            return $user->teachingClasses()->where('classes.id', $classId)->exists();
        }

        if ((int) $user->school_id !== (int) $announcement->school_id) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($user->role === 'admin') {
            return (int) $user->school_id === (int) $announcement->school_id;
        }

        if ($user->role === 'teacher') {
            return $this->update($user, $announcement);
        }

        return false;
    }

    private function normalizeRole(string $role): string
    {
        $value = strtolower(trim($role));

        return $value === 'guardian' ? 'parent' : $value;
    }
}
