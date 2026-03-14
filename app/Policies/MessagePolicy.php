<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\SchoolClass;
use App\Models\User;

class MessagePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher', 'student', 'parent'], true);
    }

    public function view(User $user, Message $message): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($message->sender_id === $user->id || $message->receiver_id === $user->id) {
            return true;
        }

        if ($user->role === 'admin' && $user->school_id) {
            return $this->isInAdminSchoolScope($user, $message);
        }

        if ($user->role === 'teacher') {
            return $message->class_id
                && $user->teachingClasses()->where('classes.id', $message->class_id)->exists();
        }

        if ($user->role === 'student') {
            return $message->class_id
                && (int) ($user->studentProfile?->class_id) === (int) $message->class_id;
        }

        if ($user->role === 'parent') {
            return $message->class_id
                && $user->children()->where('students.class_id', $message->class_id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher', 'student'], true);
    }

    public function update(User $user, Message $message): bool
    {
        return false;
    }

    public function delete(User $user, Message $message): bool
    {
        return false;
    }

    private function isInAdminSchoolScope(User $admin, Message $message): bool
    {
        if (! $admin->school_id) {
            return false;
        }

        if ($message->class_id) {
            $class = SchoolClass::query()->find($message->class_id);

            return $class && (int) $class->school_id === (int) $admin->school_id;
        }

        $sender = User::query()->find($message->sender_id);
        $receiver = $message->receiver_id ? User::query()->find($message->receiver_id) : null;

        return (bool) (
            ($sender && (int) $sender->school_id === (int) $admin->school_id)
            || ($receiver && (int) $receiver->school_id === (int) $admin->school_id)
        );
    }
}
