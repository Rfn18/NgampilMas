<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAlatRequest;
use App\Http\Requests\UpdateAlatRequest;
use App\Http\Resources\AlatResource;
use App\Models\Alat;
use App\Service\AlatService;
use Illuminate\Http\Request;

class AlatController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'q'             => ['nullable', 'string', 'max:100'],
            'kategori_id'   => ['nullable', 'integer'],
            'per_page'      => ['nullable', 'integer', 'min:1', 'max:100']
        ]);

        $alat = Alat::with('kategori')
            ->when($request->user()->isPeminjam(), fn ($q) => $q->tersedia())
            ->when($request->kategori_id, fn ($q, $id) => $q->where('kategori_id', $id))
            ->when($request->q, fn ($q, $kw) => $q->where(
                fn ($w) => $w->where("nama", "like", "%{kw}%")
                            ->orWhere("kode", "like", "%{kw}%") 
            ))
            ->orderBy('nama')
            ->paginate($request->integer('per_page', 15));

        return AlatResource::collection($alat);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAlatRequest $request, AlatService $service)
    {
        $alat = $service->store($request->validated());

        return (new AlatResource($alat))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Alat $alat)
    {
        abort_if($request->user()->isPeminjam() && ! $alat->is_active, 404);

        return new AlatResource($alat->load('kategori'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAlatRequest $request, Alat $alat, AlatService $service)
    {
        return new AlatResource($service->update($alat, $request->validated()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Alat $alat, AlatService $service)
    {
        $service->destroy($alat);

        return response()->noContent();
    }
}
