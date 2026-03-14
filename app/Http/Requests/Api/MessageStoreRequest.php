<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MessageStoreRequest extends FormRequest
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
            'receiver_id' => ['nullable', 'integer', 'exists:users,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'content' => ['required', 'string'],
            'date' => ['nullable', 'date'],
        ];
    }
}
