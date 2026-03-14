<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{userId}', function ($user, int $userId): bool {
    return (int) $user->id === $userId;
});

