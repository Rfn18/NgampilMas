<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePengembalianRequest extends FormRequest
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
            'peminjaman_id'                => ['required', 'integer', 'exists:peminjaman,id'],
            'tanggal_kembali'              => ['required', 'date', 'before_or_equal:today'],
            'items'                        => ['required', 'array', 'min:1'],
            'items.*.detail_peminjaman_id' => ['required', 'integer', 'distinct', 'exists:detail_peminjaman,id'],
            'items.*.jumlah'               => ['required', 'integer', 'min:1'],
            'items.*.kondisi'              => ['required', Rule::in(['baik', 'rusak_ringan', 'rusak_berat', 'hilang'])],
            'items.*.catatan'              => ['nullable', 'string', 'max:500'],
        ];
    }
}
