<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailPeminjamanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'jumlah'              => $this->jumlah,
            'jumlah_dikembalikan' => $this->jumlah_dikembalikan,
            'sisa'                => $this->jumlah - $this->jumlah_dikembalikan,

            'alat'        => new AlatResource($this->whenLoaded('alat')),
            'peminjaman' => new PeminjamanResource($this->whenLoaded('peminjaman')),
            'pengembalian' => PengembalianResource::collection($this->whenLoaded('pengembalian')),
        ];
    }
}
