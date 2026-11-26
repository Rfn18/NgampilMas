<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePengembalianRequest;
use App\Http\Resources\PengembalianResource;
use App\Models\Peminjaman;
use App\Models\Pengembalian;
use App\Services\PengembalianService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const RELASI = ['petugas', 'detailPeminjaman.alat', 'detailPeminjaman.peminjaman.peminjam'];
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'kondisi'       => ['nullable', Rule::in(['baik', 'rusak_ringan', 'rusak_berat', 'hilang'])],
            'q'             => ['nullable', 'string', 'max:100'],
            'peminjaman_id' => ['nullable', 'integer'],
            'dari'          => ['nullable', 'date'],
            'sampai'        => ['nullable', 'date', 'after_or_equal:dari'],
            'per_page'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $pengembalian = Pengembalian::with(self::RELASI)
            ->when($request->kondisi, fn ($q, $k) => $q->where('kondisi', $k))
            ->when($request->boolean('terlambat'), fn ($q) => $q->where('hari_terlambat', '>', 0))
            ->when($request->boolean('denda'), fn ($q) => $q->where('total_denda', '>', 0))
            ->when($request->peminjaman_id, fn ($q, $id) => $q->whereHas(
                'detailPeminjaman', fn ($d) => $d->where('peminjaman_id', $id)))
            ->when($request->q, fn ($q, $kw) => $q->whereHas(
                'detailPeminjaman.peminjaman', fn ($p) => $p->where('kode', 'like', "%{$kw}%")))
            ->when($request->dari, fn ($q, $d) => $q->whereDate('tanggal_kembali', '>=', $d))
            ->when($request->sampai, fn ($q, $d) => $q->whereDate('tanggal_kembali', '<=', $d))
            ->latest('tanggal_kembali')
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return PengembalianResource::collection($pengembalian);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePengembalianRequest $request, PengembalianService $service)
    {
        $data = $request->validated();

        $peminjaman = Peminjaman::findOrFail($data['peminjaman_id']);

        $hasil = $service->catat($peminjaman, $data['tanggal_kembali'], $data['items'], $request->user());

        return PengembalianResource::collection($hasil)->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Pengembalian $pengembalian)
    {
        return new PengembalianResource($pengembalian->load(self::RELASI));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
