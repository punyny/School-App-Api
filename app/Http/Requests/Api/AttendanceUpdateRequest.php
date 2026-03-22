<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceUpdateRequest extends FormRequest
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
            'student_id' => ['sometimes', 'integer', 'exists:students,id'],
            'class_id' => ['sometimes', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'date' => ['sometimes', 'date'],
            'time_start' => ['sometimes', 'date_format:H:i'],
            'time_end' => ['nullable', 'date_format:H:i'],
            'status' => ['sometimes', 'in:P,A,L'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
