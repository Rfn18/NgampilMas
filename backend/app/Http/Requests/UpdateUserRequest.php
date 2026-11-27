<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        
        return [
            'nama'      => ['sometimes', 'required', 'string', 'max:100'],
            'username'  => ['sometimes', 'required', 'alpha_dash', 'max:50', Rule::unique('users', 'username')->ignore($user)],
            'email'     => ['sometimes', 'required', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user)],
            'password'  => ['nullable', Password::min(8)],
            'role'      => ['sometimes', 'required', Rule::in(['admin', 'petugas', 'peminjam'])],
            'no_telp'   => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'alamat'    => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
