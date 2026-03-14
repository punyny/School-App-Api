<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class HomeworkStoreRequest extends FormRequest
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
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'question' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'file_attachments' => ['nullable', 'array'],
            'file_attachments.*' => ['nullable', 'string', 'max:500'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ];
    }
}
