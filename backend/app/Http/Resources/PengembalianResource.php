<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PengembalianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'tanggal_kembali'      => $this->tanggal_kembali->toDateString(),
            'jumlah'               => $this->jumlah,
            'kondisi'              => $this->kondisi,
            'hari_terlambat'       => $this->hari_terlambat,
            'tarif_denda_snapshot' => $this->tarif_denda_snapshot,
            'total_denda'          => $this->total_denda,
            'catatan'              => $this->catatan,

            'detail_peminjaman' => new DetailPeminjamanResource($this->whenLoaded('detailPeminjaman')),
            'petugas'           => new UserResource($this->whenLoaded('petugas')),
        ];
    }
}