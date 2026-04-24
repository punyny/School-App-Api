<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ScoreUpdateRequest extends FormRequest
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
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
            'class_id' => ['sometimes', 'integer', 'exists:classes,id'],
            'exam_score' => ['sometimes', 'numeric', 'min:0', 'max:1000'],
            'total_score' => ['sometimes', 'numeric', 'min:0', 'max:1000'],
            'assessment_type' => ['nullable', 'in:monthly,semester'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'semester' => ['nullable', 'integer', 'between:1,2'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'period' => ['nullable', 'string', 'max:50'],
            'grade' => ['nullable', 'string', 'max:5'],
            'rank_in_class' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
