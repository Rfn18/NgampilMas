<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKategoriRequest;
use App\Http\Requests\UpdateKategoriRequest;
use App\Http\Resources\KategoriResource;
use App\Models\Kategori;
use App\Models\LogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KategoriController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $kategori = Kategori::withCount('alat')
                ->when($request->q, fn ($q, $kw) => $q->where('nama', 'like', "%{$kw}%"))
                ->orderBy('nama')
                ->get();
        
        return KategoriResource::collection($kategori);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreKategoriRequest $request)
    {
         $kategori = DB::transaction(function () use ($request) {
            $k = Kategori::create($request->validated());
            
            LogAktivitas::catat('create', 'kategori', $k->id, null, $k->only(['nama', 'deskripsi']));

            return $k;
        });

        return (new KategoriResource($kategori))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Kategori $kategori)
    {
       return new KategoriResource($kategori->loadCount('alat'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateKategoriRequest $request, Kategori $kategori)
    {
        DB::transaction(function () use ($request, $kategori) {
            $lama = $kategori->only(['nama', 'deskripsi']);

            $kategori->update($request->validated());

            LogAktivitas::catat('update', 'kategori', $kategori->id, $lama, $kategori->only(['nama','deskripsi']));
        });

        return new KategoriResource($kategori->loadCount('alat'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Kategori $kategori)
    {
        if ($kategori->alat()->exists()) {
            throw ValidationException::withMessages([
                'kategori' => 'Kategori tidak bisa dihapus karena masih dimiliki data alat.'
            ]);
        }

        DB::transaction(function () use ($kategori) {
            $lama = $kategori->only(['name', 'description']);

            $kategori->delete();

            LogAktivitas::catat('delete', 'kategori', $kategori->id, $lama);
        });

        return response()->noContent();
    }
}
