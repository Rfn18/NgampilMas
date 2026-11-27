<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectPeminjamanRequest;
use App\Http\Requests\StorePeminjamanRequest;
use App\Http\Requests\UpdatePeminjamanRequest;
use App\Http\Resources\PeminjamanResource;
use App\Models\Peminjaman;
use App\Service\PeminjamanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeminjamanController extends Controller
{

    private const RELASI = ['peminjam', 'petugas', 'detail.alat'];
    
    public function index(Request $request)
    {
        $request->validate([
            'status'    => ['nullable', Rule::in(['diajukan', 'disetujui', 'ditolak', 'dipinjam', 'selesai', 'dibatalkan'])],
            'q'         => ['nullable', 'string:100'],
            'dari'      => ['nullable', 'date'],
            'sampai'    => ['nullable', 'date', 'after_or_equal:dari'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $peminjaman = Peminjaman::with(['peminjam', 'detail.alat'])
                    ->when($request->user()->isPeminjam(),
                        fn ($q) => $q->where('peminjam_id', $request->user()->id))
                    ->when($request->status, fn ($q, $s) => $q->where('status', '$s'))
                    ->when($request->boolean('terlambat'), fn ($q) => $q->terlambat())
                    ->when($request->q, fn ($q, $kw) => $q->where(fn ($w) => $w
                        ->where('kode', 'like', "%{$kw}%")
                        ->orWhereHas('peminjam', fn ($u) => $u->where('nama', 'like', "%{$kw}%"))))
                    ->when($request->dari, fn ($q, $d) => $q->whereDate('tanggal_pinjam', '>=', $d))
                    ->when($request->sampai, fn ($q, $d) => $q->whereDate('tanggal_pinjam', '<=', $d))
                    ->latest()
                    ->paginate($request->integer('per_page', 15));
        return PeminjamanResource::collection($peminjaman);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePeminjamanRequest $request, PeminjamanService $service)
    {
        $p = $service->store($request->user(), $request->validated());

        return (new PeminjamanResource($p))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Peminjaman $peminjaman)
    {
        abort_if(
            $request->user()->isPeminjam() && $peminjaman->peminjam_id !== $request->user()->id,
            403,
        );

        return new PeminjamanResource($peminjaman->load([...self::RELASI, 'detail.pengembalian']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePeminjamanRequest $request, Peminjaman $peminjaman, PeminjamanService $service)
    {
        return new PeminjamanResource($service->update($peminjaman, $request->validated()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Peminjaman $peminjaman, PeminjamanService $service)
    {
        $service->destroy($peminjaman);

        return response()->noContent();
    }

    public function approve(Request $request, Peminjaman $peminjaman, PeminjamanService $service)
    {
        $p = $service->approve($peminjaman, $request->user());

        return new PeminjamanResource($p->load(self::RELASI));
    }

    public function reject(RejectPeminjamanRequest $request, Peminjaman $peminjaman, PeminjamanService $service)
    {
        $p = $service->reject($peminjaman, $request->user(), $request->validated('catatan_petugas'));

        return new PeminjamanResource($p->load(self::RELASI));
    }

    public function cancel(Request $request, Peminjaman $peminjaman, PeminjamanService $service)
    {
        $p = $service->cancel($peminjaman, $request->user());

        return new PeminjamanResource($p->load(self::RELASI));
    }
}
