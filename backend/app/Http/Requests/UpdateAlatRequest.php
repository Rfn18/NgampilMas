<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlatRequest extends FormRequest
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
        $alat = $this->route('alat');   
        
        return [
            'kategori_id'          => ['sometimes', 'required', Rule::exists('kategori', 'id')->whereNull('deleted_at')],
            'kode'                 => ['sometimes', 'required', 'string', 'max:30', Rule::unique('alat', 'kode')->ignore($alat)],
            'nama'                 => ['sometimes', 'required', 'string', 'max:150'],
            'deskripsi'            => ['nullable', 'string'],
            'foto'                 => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'stok_total'           => ['sometimes', 'required', 'integer', 'min:0'],
            'kondisi'              => ['sometimes', 'required', Rule::in(['baik', 'rusak_ringan', 'rusak_berat'])],
            'tarif_denda_per_hari' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active'            => ['sometimes', 'required', 'boolean'],
        ];
    }
}
