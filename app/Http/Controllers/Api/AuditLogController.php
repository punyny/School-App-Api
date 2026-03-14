<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'actor_role' => ['nullable', 'in:super-admin,admin,teacher,student,parent'],
            'method' => ['nullable', 'in:GET,POST,PUT,PATCH,DELETE'],
            'resource_type' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $authUser = $request->user();

        $query = AuditLog::query()
            ->with(['actor:id,name,email,role', 'school:id,name'])
            ->latest('id');

        if ($authUser->role === 'admin') {
            $query->where('school_id', (int) $authUser->school_id);
        }

        foreach (['actor_id', 'actor_role', 'method', 'resource_type'] as $filterKey) {
            if (! isset($filters[$filterKey])) {
                continue;
            }
            $query->where($filterKey, $filters[$filterKey]);
        }

        if (isset($filters['search'])) {
            $query->where(function ($scope) use ($filters): void {
                $scope->where('actor_name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('action', 'like', '%'.$filters['search'].'%')
                    ->orWhere('endpoint', 'like', '%'.$filters['search'].'%');
            });
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }
}

