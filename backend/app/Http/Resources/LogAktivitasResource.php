<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogAktivitasResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'aksi'       => $this->aksi,
            'modul'      => $this->modul,
            'data_id'    => $this->data_id,
            'data_lama'  => $this->data_lama,
            'data_baru'  => $this->data_baru,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at->toIso8601String(),

            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}