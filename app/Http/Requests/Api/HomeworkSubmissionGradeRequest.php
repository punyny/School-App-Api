<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class HomeworkSubmissionGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teacher_score' => ['required', 'numeric', 'min:0'],
            'teacher_score_max' => ['required', 'numeric', 'gt:0'],
            'score_weight_percent' => ['required', 'numeric', 'between:0,100'],
            'assessment_type' => ['required', 'in:monthly,semester'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'semester' => ['nullable', 'integer', 'between:1,2'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'teacher_feedback' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $assessmentType = (string) $this->input('assessment_type', '');

            if ($assessmentType === 'monthly' && ! $this->filled('month')) {
                $validator->errors()->add('month', 'month is required when assessment_type is monthly.');
            }

            if ($assessmentType === 'semester' && ! $this->filled('semester')) {
                $validator->errors()->add('semester', 'semester is required when assessment_type is semester.');
            }
        });
    }
}
