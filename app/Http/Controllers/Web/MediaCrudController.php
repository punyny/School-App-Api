<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'string', 'max:50'],
            'mediable_type' => ['nullable', 'string', 'max:150'],
            'mediable_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/media', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if (($result['status'] ?? 0) !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.media.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
            'userRole' => $request->user()->role,
        ]);
    }

    public function destroy(Request $request, int $media, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/media/'.$media);

        if (($result['status'] ?? 0) !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.media.index', [], false))->with('success', 'Media deleted successfully.');
    }
}
