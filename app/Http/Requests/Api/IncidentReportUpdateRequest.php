<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class IncidentReportUpdateRequest extends FormRequest
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
            'description' => ['sometimes', 'string'],
            'date' => ['sometimes', 'date'],
            'type' => ['nullable', 'string', 'max:100'],
            'acknowledged' => ['nullable', 'boolean'],
        ];
    }
}
