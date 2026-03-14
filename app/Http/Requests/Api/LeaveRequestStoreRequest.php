<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LeaveRequestStoreRequest extends FormRequest
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
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
            'request_type' => ['required', 'in:hourly,multi_day'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_if:request_type,hourly'],
            'end_time' => ['nullable', 'date_format:H:i', 'required_if:request_type,hourly'],
            'return_date' => ['nullable', 'date', 'after_or_equal:end_date', 'required_if:request_type,multi_day'],
            'total_days' => ['nullable', 'integer', 'min:1', 'required_if:request_type,multi_day'],
            'reason' => ['required', 'string'],
        ];
    }
}
