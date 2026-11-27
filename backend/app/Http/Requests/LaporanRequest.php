<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LaporanRequest extends FormRequest
{
   
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dari'        => ['required', 'date'],
            'sampai'      => ['required', 'date', 'after_or_equal:dari'],
            'status'      => ['nullable', Rule::in(['diajukan', 'disetujui', 'ditolak', 'dipinjam', 'selesai', 'dibatalkan'])],
            'peminjam_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $dari   = $this->date('dari');
                $sampai = $this->date('sampai');

                if ($dari && $sampai && $dari->diffInDays($sampai) > 366) {
                    $validator->errors()->add('sampai', 'Rentang laporan maksimal 1 tahun.');
                }
            },
        ];
    }
}
