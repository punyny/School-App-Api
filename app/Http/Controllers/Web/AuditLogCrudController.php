<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'actor_id' => ['nullable', 'integer'],
            'actor_role' => ['nullable', 'in:super-admin,admin,teacher,student,parent'],
            'method' => ['nullable', 'in:GET,POST,PUT,PATCH,DELETE'],
            'resource_type' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/audit-logs', array_filter($filters, fn ($v) => $v !== null && $v !== ''));
        if (($result['status'] ?? 0) !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.audit-logs.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
            'userRole' => $request->user()->role,
        ]);
    }
}

