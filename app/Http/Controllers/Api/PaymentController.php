<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'student_fee_id' => ['nullable', 'integer', 'exists:student_fees,student_fee_id'],
            'payment_method' => ['nullable', 'in:Cash,Bank,Card,Mobile Money'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Payment::query()
            ->with(['studentFee.enrollment.student.user', 'studentFee.feeType', 'receiver'])
            ->orderByDesc('payment_id');

        if (isset($filters['student_fee_id'])) {
            $query->where('student_fee_id', (int) $filters['student_fee_id']);
        }
        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json(['data' => $payment->load(['studentFee.enrollment.student.user', 'studentFee.feeType', 'receiver'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'student_fee_id' => ['required', 'integer', 'exists:student_fees,student_fee_id'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:Cash,Bank,Card,Mobile Money'],
            'reference_no' => ['nullable', 'string', 'max:50'],
        ]);

        $payload['received_by'] = $request->user()?->id;
        $payment = Payment::query()->create($payload);

        return response()->json(['message' => 'Payment created.', 'data' => $payment], 201);
    }

    public function update(Request $request, Payment $payment): JsonResponse
    {
        $payload = $request->validate([
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'in:Cash,Bank,Card,Mobile Money'],
            'reference_no' => ['nullable', 'string', 'max:50'],
        ]);

        $payment->fill($payload)->save();

        return response()->json(['message' => 'Payment updated.', 'data' => $payment]);
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $payment->delete();

        return response()->json(['message' => 'Payment deleted.']);
    }
}

