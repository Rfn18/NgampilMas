<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PeminjamanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'kode'                => $this->kode,
            'status'              => $this->status,
            'tanggal_pengajuan'   => $this->tanggal_pengajuan->toDateString(),
            'tanggal_pinjam'      => $this->tanggal_pinjam->toDateString(),
            'tanggal_jatuh_tempo' => $this->tanggal_jatuh_tempo->toDateString(),
            'keperluan'           => $this->keperluan,
            'catatan_petugas'     => $this->catatan_petugas,
            'disetujui_at'        => $this->disetujui_at?->toIso8601String(),
            'is_terlambat'        => $this->is_terlambat,

            'peminjam' => new UserResource($this->whenLoaded('peminjam')),
            'petugas'  => new UserResource($this->whenLoaded('petugas')),
            'detail'   => DetailPeminjamanResource::collection($this->whenLoaded('detail')),
        ];
    }
}
