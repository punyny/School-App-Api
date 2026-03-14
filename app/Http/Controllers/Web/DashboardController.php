<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $role = $request->user()?->normalizedRole() ?? '';

        return match ($role) {
            'super-admin' => redirect()->away(route('super-admin.dashboard', [], false)),
            'admin' => redirect()->away(route('admin.dashboard', [], false)),
            'teacher' => redirect()->away(route('teacher.dashboard', [], false)),
            'student' => redirect()->away(route('student.dashboard', [], false)),
            'parent', 'guardian' => redirect()->away(route('parent.dashboard', [], false)),
            default => abort(403, 'Forbidden for this role.'),
        };
    }
}
