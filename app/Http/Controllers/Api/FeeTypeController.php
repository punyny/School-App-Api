<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeeType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = FeeType::query()->orderBy('fee_name');
        if (! empty($filters['search'])) {
            $query->where('fee_name', 'like', '%'.$filters['search'].'%');
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(FeeType $feeType): JsonResponse
    {
        return response()->json(['data' => $feeType]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'fee_name' => ['required', 'string', 'max:50', 'unique:fee_types,fee_name'],
            'description' => ['nullable', 'string', 'max:255'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $feeType = FeeType::query()->create($payload);

        return response()->json(['message' => 'Fee type created.', 'data' => $feeType], 201);
    }

    public function update(Request $request, FeeType $feeType): JsonResponse
    {
        $payload = $request->validate([
            'fee_name' => ['nullable', 'string', 'max:50', 'unique:fee_types,fee_name,'.$feeType->fee_type_id.',fee_type_id'],
            'description' => ['nullable', 'string', 'max:255'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $feeType->fill($payload)->save();

        return response()->json(['message' => 'Fee type updated.', 'data' => $feeType]);
    }

    public function destroy(FeeType $feeType): JsonResponse
    {
        $feeType->delete();

        return response()->json(['message' => 'Fee type deleted.']);
    }
}

