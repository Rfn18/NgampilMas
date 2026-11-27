<?php

namespace App\Service;

use App\Models\Alat;
use App\Models\LogAktivitas;
use App\Models\Peminjaman;
use App\Models\Pengembalian;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PengembalianService
{
    public function catat(Peminjaman $peminjaman, string $tanggalKembali, array $items, User $petugas): Collection
    {
        return DB::transaction(function () use ($peminjaman, $tanggalKembali, $items, $petugas) {
            $p = Peminjaman::with('detail')->lockForUpdate()->findOrFail($peminjaman->id);

            if ($p->status !== 'dipinjam') {
                throw ValidationException::withMessages([
                    'status' => 'Yang dikembalikan harus barang yang sedang dipinjam.'
                ]);
            }

            $tanggal = Carbon::parse($tanggalKembali)->startOfDay();
            if ($tanggal->lt($p->tanggal_pinjam)) {
                throw ValidationException::withMessages([
                      'tanggal_kembali' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam.',
                ]);
            }

            $terlambat = max(0, (int) $p->tanggal_jatuh_tempo->diffInDays($tanggal, false));

            $hasil = new Collection();

            foreach($items as $item) {
                $d = $p->detail->firstWhere('id', $item['detail_peminjaman_id']);

                if (! $d) {
                    throw ValidationException::withMessages([
                        'items' => 'Ada item yang bukan bagian dari peminjaman ini.'
                    ]);
                }

                $sisa = $d->jumlah - $d->jumlah_dikembalikan;
                if ($item['jumlah'] > $sisa) {
                    throw ValidationException::withMessages([
                        'items' => "Jumlah melebihi sisa yang belum dikembalikan ({$sisa})."
                    ]);
                }

                $alat = Alat::lockForUpdate()->findOrFail($d->alat_id);

                $tarif = $alat->tarif_denda_per_hari;

                $pengembalian = Pengembalian::create([
                    'detail_peminjaman_id'      => $d->id,
                    'petugas_id'                => $petugas->id,
                    'tanggal_kembali'           => $tanggal,
                    'jumlah'                    => $item['jumlah'],
                    'kondisi'                   => $item['kondisi'],
                    'hari_terlambat'            => $terlambat,
                    'tarif_denda_snapshot'      => $tarif,
                    'total_denda'               => $terlambat * $tarif * $item['jumlah'],
                    'catatan'                   => $item['catatan'] ?? null
                ]);

                $d->increment('jumlah_dikembalikan', $item['jumlah']);

                if (in_array($item['kondisi'], ['baik', 'rusak_ringan'], true)) {
                    $alat->increment('stok_tersedia', $item['jumlah']);
                } else {
                    $alat->decrement('stok_total', $item['jumlah']);
                }

                LogAktivitas::catat('return', 'pengembalian', $pengembalian->id);

                $hasil->push($pengembalian);
            }

            $masihAda = $p->detail()->whereColumn('jumlah_dikembalikan', '<', 'jumlah')->exists();
            if(!$masihAda) {
                $p->update(['status' => 'selesai']);
            }

            return $hasil->load(['petugas', 'detailPeminjaman.alat']);
        });
    }
}