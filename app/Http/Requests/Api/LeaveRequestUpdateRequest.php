<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LeaveRequestUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['sometimes', 'nullable', 'integer', 'exists:subjects,id'],
            'subject_ids' => ['sometimes', 'array', 'min:1'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
            'request_type' => ['sometimes', 'in:hourly,multi_day'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'return_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:end_date'],
            'total_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'reason' => ['sometimes', 'required', 'string'],
        ];
    }
}
