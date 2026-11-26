<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlatRequest extends FormRequest
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
            'kategori_id'          => ['required', Rule::exists('kategori', 'id')->whereNull('deleted_at')],
            'kode'                 => ['required', 'string', 'max:30', 'unique:alat,kode'],
            'nama'                 => ['required', 'string', 'max:150'],
            'deskripsi'            => ['nullable', 'string'],
            'foto'                 => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'stok_total'           => ['required', 'integer', 'min:0'],
            'kondisi'              => ['nullable', Rule::in(['baik', 'rusak_ringan', 'rusak_berat'])],
            'tarif_denda_per_hari' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active'            => ['nullable', 'boolean'],
        ];
    }
}
