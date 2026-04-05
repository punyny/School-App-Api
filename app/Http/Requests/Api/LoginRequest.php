<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class LoginRequest extends FormRequest
{
    private const METHOD_PASSWORD = 'password';
    private const METHOD_ACCESS_TOKEN = 'access_token';
    private const METHOD_MAGIC_LINK = 'magic_link';

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
            'auth_method' => ['nullable', 'string', 'in:password,access_token,magic_link'],
            'login' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'id' => ['nullable', 'integer', 'exists:users,id'],
            'token' => ['nullable', 'string', 'min:32', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        $login = $this->input('login');
        $authMethod = $this->input('auth_method');
        $token = $this->input('token');
        $deviceName = $this->input('device_name');

        $normalized = [];
        if (is_string($email)) {
            $normalized['email'] = Str::lower(trim($email));
        }

        if (is_string($login)) {
            $normalized['login'] = trim($login);
        }

        if (is_string($authMethod)) {
            $normalized['auth_method'] = Str::lower(trim($authMethod));
        }

        if (is_string($token)) {
            $normalized['token'] = trim($token);
        }

        if (is_string($deviceName)) {
            $normalized['device_name'] = trim($deviceName);
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $method = $this->resolvedMethod();
            $hasPasswordIdentity = filled($this->input('login')) || filled($this->input('email'));
            $hasPassword = filled($this->input('password'));
            $hasId = filled($this->input('id'));
            $hasToken = filled($this->input('token'));
            $hasAccessTokenPayload = $hasId || $hasToken;

            if (! $hasPasswordIdentity && ! $hasPassword && ! $hasAccessTokenPayload) {
                $validator->errors()->add(
                    'login',
                    'Provide either email/login + password or id + access token.'
                );

                return;
            }

            if (($hasPasswordIdentity || $hasPassword) && $hasAccessTokenPayload) {
                $validator->errors()->add(
                    'token',
                    'Use only one login option: email/password or access token.'
                );

                return;
            }

            if ($method === self::METHOD_PASSWORD) {
                if (! $hasPasswordIdentity) {
                    $validator->errors()->add(
                        'login',
                        'Email or username is required for password login.'
                    );
                }

                if (! $hasPassword) {
                    $validator->errors()->add(
                        'password',
                        'Password is required for password login.'
                    );
                }

                if ($hasId || $hasToken) {
                    $validator->errors()->add(
                        'token',
                        'Use only one login option: email/password or access token.'
                    );
                }

                return;
            }

            if (! $hasId) {
                $validator->errors()->add(
                    'id',
                    'User id is required for access token login.'
                );
            }

            if (! $hasToken) {
                $validator->errors()->add(
                    'token',
                    'Access token is required for access token login.'
                );
            }

            if ($hasPasswordIdentity || $hasPassword) {
                $validator->errors()->add(
                    'token',
                    'Use only one login option: email/password or access token.'
                );
            }
        });
    }

    private function resolvedMethod(): string
    {
        $authMethod = (string) ($this->input('auth_method') ?? '');

        if (in_array($authMethod, [self::METHOD_PASSWORD, self::METHOD_ACCESS_TOKEN], true)) {
            return $authMethod;
        }

        if ($authMethod === self::METHOD_MAGIC_LINK) {
            return self::METHOD_ACCESS_TOKEN;
        }

        return filled($this->input('token')) || filled($this->input('id'))
            ? self::METHOD_ACCESS_TOKEN
            : self::METHOD_PASSWORD;
    }
}
