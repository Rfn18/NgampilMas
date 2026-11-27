<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LaporanRequest;
use App\Http\Resources\PeminjamanResource;
use App\Http\Resources\PengembalianResource;
use App\Models\Peminjaman;
use App\Models\Pengembalian;

class LaporanController extends Controller
{
    public function peminjaman(LaporanRequest $request)
    {
        $f = $request->validated();

        $base = Peminjaman::query()
            ->whereDate('tanggal_pinjam', '>=', $f['dari'])
            ->whereDate('tanggal_pinjam', '<=', $f['sampai'])
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['peminjam_id'] ?? null, fn ($q, $id) => $q->where('peminjam_id', $id));
        
        $perStatus = (clone $base) 
            ->selectRaw('status, COUNT(*) as TOTAL')
            ->groupBy('status')
            ->pluck('total', 'status');
        
        $data = $base
            ->with(['peminjam', 'petugas', 'detail.alat'])
            ->orderBy('tanggal_pinjam')
            ->orderBy('id')
            ->get();

        return PeminjamanResource::collection($data)->additional([
            'ringkasan' => [
                'periode'       => ['dari' => $f['dari'], 'sampai' => $f['sampai']],
                'total'         => $data->count(),
                'per_status'    => $perStatus  ,
            ]
        ]);
    }

    public function pengembalian(LaporanRequest $request)
    {
        $f = $request->validated();

        $base = Pengembalian::query()
            ->whereDate('tanggal_kembali', '>=', $f['dari'])
            ->whereDate('tanggal_kembali', '<=', $f['sampai'])
            ->when($f['peminjam_id'] ?? null, fn ($q, $id) => $q->whereHas(
                'detailPeminjaman.peminjaman', fn ($p) => $p->where('peminjam_id', $id)
            ));

        $total = (clone $base)
            ->selectRaw('
                COUNT(*) as total_catatan,
                COALESCE(SUM(JUMLAH), 0) as total_unit,
                COALESCE(SUM(total_denda), 0, as total_denda,
                COALESCE(SUM(CASE when hari_terlambat > 0 THEN 1 ELSE 0 END), 0) as catatan_terlambat')
            ->first();

        $perKondisi = (clone $base)
            ->selectRaw('kondisi, SUM(jumlah) as total_unit')
            ->groupBy('kondisi')
            ->pluck('total_unit', 'kondisi');

        $data = $base
            ->with(['petugas', 'detailPeminjaman.alat', 'detailPeminjaman.peminjaman.peminjam'])
            ->orderBy('tanggal_kembali')
            ->orderBy('id')
            ->get();

        return PengembalianResource::collection($data)->additional([
            'ringkasan' => [
                'periode'           => ['dari' => $f['dari'], 'sampai' => $f['sampai']],
                'total_catatan'     => (int) $total->total_catatan,
                'total_unit'        => (int) $total->total_unit,
                'total_denda'       => $total->total_denda,
                'catatan_terlambat' => (int) $total->catatan_terlambat,
                'per_kondisi'       => $perKondisi,
            ]
        ]);
    }
}
