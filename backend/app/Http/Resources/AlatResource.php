<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AlatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'kode'                 => $this->kode,
            'nama'                 => $this->nama,
            'deskripsi'            => $this->deskripsi,
            'foto_url'             => $this->foto ? Storage::url($this->foto) : null,
            'stok_total'           => $this->stok_total,
            'stok_tersedia'        => $this->stok_tersedia,
            'kondisi'              => $this->kondisi,
            'tarif_denda_per_hari' => $this->tarif_denda_per_hari,
            'is_active'            => $this->is_active,
            'kategori'             => new KategoriResource($this->whenLoaded('kategori')),
        ];
    }
}