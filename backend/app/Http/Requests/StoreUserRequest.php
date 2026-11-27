<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
        return [
            'nama'      => ['required', 'string', 'max:100'],
            'username'  => ['required', 'alpha_dash', 'max:50', 'unique:users,username'],
            'email'     => ['required', 'email', 'max:100', 'unique:users,email'],
            'password'  => ['required', Password::min(8)],
            'role'      => ['required', Rule::in(['admin', 'petugas', 'peminjam'])],
            'no_telp'   => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'alamat'    => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
