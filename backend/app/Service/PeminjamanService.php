<?php

namespace App\Services;

use App\Models\Alat;
use App\Models\LogAktivitas;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class PeminjamanService
{
    public function store(User $user, array $data): Peminjaman
    {
        return DB::transaction(function () use ($user, $data) {
            $peminjamId = $user->isAdmin() ? $data['peminjam_id'] : $user->id;

            $this->cekStok($data['items']);

            $p = Peminjaman::create([
                'kode'                  => $this->buatKode(),
                'peminjam_id'           => $peminjamId,
                'tanggal_pengajuan'     => now(),
                'tanggal_pinjam'        => $data['tanggal_pinjam'],
                'tanggal_jatuh_tempo'   => $data['tanggal_jatuh_tempo'],
                'keperluan'             => $data['keperluan'] ?? null,
                'status'                => 'diajukan'
            ]);

            foreach ($data['items'] as $item) {
                $p->detail()->create([
                    'alat_id'   => $item['alat_id'],
                    'jumlah'    => $item['jumlah']
                ]);
            }

            LogAktivitas::catat('create', 'peminjaman', $p->id, null, [
                'kode'  => $p->kode,
                'items' => $data['items']
            ]);

            return $p->load(['peminjam', 'detail.alat']);
        });
    }

    public function update(Peminjaman $peminjaman, array $data): Peminjaman
    {
        return DB::transaction(function () use ($peminjaman, $data) {
            $p = Peminjaman::with('detail')->lockForUpdate()->findOrFail($peminjaman->id);

            if ($p->status !== 'diajukan') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya peminjaman berstatus diajukan yang bisa diubah.'
                ]);
            }

            $this->cekStok($data['items']);

            $lama = $this->ringkas($p);

            $p->update([
                'tanggal_pinjam'        => $data['tanggal_pinjam'],
                'tanggal_jatuh_tempo'   => $data['tanggal_jatuh_tempo'],
                'keperluan'             => $data['keperluan'] ?? null,
            ]);

            $p->detail()->delete(); 
            foreach($data['items'] as $item) {
                $p->detail()->create([
                    'alat_id'   => $item['alat_id'],
                    'jumlah'    => $item['jumlah']
                ]);
            }

            $p->load('detail');
            LogAktivitas::catat('update', 'peminjaman', $p->id, $lama, $this->ringkas($p));

            return $p->load(['peminjam', 'detail.alat']);
        });
    }

    public function destroy(Peminjaman $peminjaman): void
    {
        DB::transaction(function () use ($peminjaman) {
            $p = Peminjaman::with('detail')->lockForUpdate()->findOrFail($peminjaman->id);
            
            if (! in_array($p->status, ['diajukan', 'ditolak', 'dibatalkan'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya peminjaman berstatus diajukan, ditolak, atau dibatalkan yang bisa dihapus.',
                ]);
            }

            $lama = $this->ringkas($p);

            $p->delete();

            LogAktivitas::catat('delete', 'peminjaman', $p->id, $lama);
        });
    }

    public function reject(Peminjaman $peminjaman, User $petugas, string $catatan): Peminjaman 
    {
        return DB::transaction(function () use ($peminjaman, $petugas, $catatan) {
            $p = Peminjaman::lockForUpdate()->findOrFail($peminjaman->id);

            if ($p->status !== 'diajukan') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya peminjaman berstatus diajukan yang bisa ditolak'
                ]);
            }

            $p->update([
                'status' => 'ditolak',
                'petugas_id' => $petugas->id,
                'catatan_petugas' => $catatan
            ]);

            LogAktivitas::catat('reject', 'peminjaman', $p->id);

            return $p;
        });
    }

    public function cancel(Peminjaman $peminjaman, User $user): Peminjaman {
        return DB::transaction(function () use ($peminjaman, $user) {
            $p = Peminjaman::lockForUpdate()->findOrFail($peminjaman->id);

            if (!$user->isAdmin() && $p->peminjam_id !== $user->id) {
                throw new AuthorizationException('Pembatalan hanya boleh dilakukan oleh peminjam yang mengajukan.');
            }

            if ($p->status !== 'diajukan') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya peminjaman berstatus diajukan yang bisa dibatalkan.',
                ]);
            }

            $p->update(['status' => 'dibatalkan']);

            LogAktivitas::catat('cancel', 'peminjaman', $p->id);

            return $p;
        });
    }

    public function approve(Peminjaman $peminjaman, User $petugas): Peminjaman
    {
        return DB::transaction(function () use ($peminjaman, $petugas) {

            $p = Peminjaman::with('detail')->lockForUpdate()->findOrFail($peminjaman->id);

            if ($p->status !== 'diajukan') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya peminjaman berstatus diajukan yang bisa disetujui.',
                ]);
            }

            foreach ($p->detail->sortBy('alat_id') as $d) {
                $alat = Alat::lockForUpdate()->findOrFail($d->alat_id);

                if (! $alat->is_active || $alat->stok_tersedia < $d->jumlah) {
                    throw ValidationException::withMessages([
                        'stok' => "Stok {$alat->nama} tidak mencukupi "
                                . "(tersedia {$alat->stok_tersedia}, diminta {$d->jumlah}).",
                    ]);
                }

                $alat->decrement('stok_tersedia', $d->jumlah);
            }

            $p->update([
                'status'       => 'dipinjam',
                'petugas_id'   => $petugas->id,
                'disetujui_at' => now(),
            ]);

            LogAktivitas::catat('approve', 'peminjaman', $p->id);

            return $p;
        });
    }

    private function buatKode():string 
    {
        do {
            $kode = 'PJM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
        } while (Peminjaman::where('kode', $kode)->exists());

        return $kode;
    }

    private function ringkas(Peminjaman $p): array 
    {
        return [
            'kode'                  => $p->kode,
            'peminjam_id'           => $p->peminjam_id,
            'status'                => $p->status,
            'tanggal_pinjam'        => $p->tanggal_pinjam->toDateString(),
            'tanggal_jatuh_tempo'   => $p->tanggal_jatuh_tempo->toDateString(),
            'keperluan'             => $p->keperluan,
            'items'                 => $p->detail->map(fn ($d) => [
                'alat_id'   => $d->alat_id,
                'jumlah'    => $d->jumlah
            ])->all(),
        ];
    }

    private function cekStok(array $items): void {
        foreach ($items as $item) {
            $alat = Alat::findOrFail($item['alat_id']);

            if (!$alat->is_active || $alat->stok_tersedia < $item['jumlah']) {
                throw ValidationException::withMessages([
                    "items" => "Stok {$alat->nama} tidak mencukupi (tersedia {$alat->stok_tersedia}).",
                ]);
            }
        }
    }
}
