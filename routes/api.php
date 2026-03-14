<?php

use Illuminate\Support\Facades\Route;

// Backward-compatible unversioned API routes.
Route::group([], base_path('routes/api_core.php'));

// Versioned API routes for future changes.
Route::prefix('v1')->group(base_path('routes/api_core.php'));
