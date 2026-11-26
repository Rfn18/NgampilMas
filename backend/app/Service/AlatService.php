<?php

namespace App\Services;

use App\Models\Alat;
use App\Models\LogAktivitas;
use Illuminate\Support\Arr;
use App\Models\Peminjaman;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AlatService
{
    public function store(array $data): Alat
    {
       if (! empty($data['foto'])) {
            $data['foto'] = $data['foto']->store('alat', 'public');
        }

         return DB::transaction(function () use ($data) {
            $a = Alat::create([
                'kategori_id'          => $data['kategori_id'],
                'kode'                 => $data['kode'],
                'nama'                 => $data['nama'],
                'deskripsi'            => $data['deskripsi'] ?? null,
                'foto'                 => $data['foto'] ?? null,
                'stok_total'           => $data['stok_total'],
                'stok_tersedia'        => $data['stok_total'],
                'kondisi'              => $data['kondisi'] ?? 'baik',
                'tarif_denda_per_hari' => $data['tarif_denda_per_hari'] ?? 0,
                'is_active'            => $data['is_active'] ?? true,
            ]);

            LogAktivitas::catat('create', 'alat', $a->id, null, $a->only(['kode', 'nama', 'stok_total']));

            return $a->load('kategori');
        });
    }

    public function update(Alat $alat, array $data): Alat
    {
        return DB::transaction(function () use ($alat, $data) {

            $a = Alat::lockForUpdate()->findOrFail($alat->id);

            $stokTotal = $data['stok_total'] ?? $a->stok_total;
            $dipinjam  = $a->stok_total - $a->stok_tersedia;

            if ($stokTotal < $dipinjam) {
                throw ValidationException::withMessages([
                    'stok_total' => "Stok total tidak boleh kurang dari {$dipinjam} (jumlah yang sedang dipinjam).",
                ]);
            }

            $lama = $this->ringkas($a);

            $fotoLama = $a->foto;
            $fotoBaru = ! empty($data['foto'])
                ? $data['foto']->store('alat', 'public')
                : null;

            $a->update([
                ...Arr::only($data, [
                    'kategori_id', 'kode', 'nama', 'deskripsi',
                    'kondisi', 'tarif_denda_per_hari', 'is_active',
                ]),
                'foto'          => $fotoBaru ?? $fotoLama,
                'stok_total'    => $stokTotal,
                'stok_tersedia' => $stokTotal - $dipinjam,
            ]);

            if ($fotoBaru && $fotoLama) {
                Storage::disk('public')->delete($fotoLama);
            }

            LogAktivitas::catat('update', 'alat', $a->id, $lama, $this->ringkas($a));

            return $a->load('kategori');
        });
    }

    public function destroy(Alat $alat): void
    {
        DB::transaction(function () use ($alat) {
            $a = Alat::lockForUpdate()->findOrFail($alat->id);

           $masihDipakai = Peminjaman::whereIn('status', ['diajukan', 'dipinjam'])
            ->whereHas('detail', fn ($q) => $q->where('alat_id', $a->id))
            ->exists();

            if ($masihDipakai) {
                throw ValidationException::withMessages([
                    'alat' => 'Alat tidak bisa dihapus karena masih ada peminjaman yang diajukan atau sedang berjalan.',
                ]);
            }

            $lama = $this->ringkas($a);

            $a->delete();

            LogAktivitas::catat('delete', 'alat', $a->id, $lama);
        });
    }

    private function ringkas(Alat $a): array
    {
        return [
            'kategori_id'          => $a->kategori_id,
            'kode'                 => $a->kode,
            'nama'                 => $a->nama,
            'deskripsi'            => $a->deskripsi,
            'foto'                 => $a->foto,
            'stok_total'           => $a->stok_total,
            'stok_tersedia'        => $a->stok_tersedia,
            'kondisi'              => $a->kondisi,
            'tarif_denda_per_hari' => $a->tarif_denda_per_hari,
            'is_active'            => $a->is_active,
        ];
    }
}