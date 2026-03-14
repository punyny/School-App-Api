<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AnnouncementUpdateRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'date' => ['sometimes', 'date'],
            'school_id' => ['sometimes', 'integer', 'exists:schools,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'target_role' => ['nullable', 'in:teacher,student,parent'],
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'file_attachments' => ['nullable', 'array'],
            'file_attachments.*' => ['nullable', 'string', 'max:500'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ];
    }
}
