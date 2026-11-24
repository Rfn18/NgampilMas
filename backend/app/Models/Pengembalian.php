<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengembalian extends Model
{
    protected $table = 'pengembalians';

    protected $fillable = [
        'detail_peminjaman_id',
        'petugas_id',
        'tanggal_kembali',
        'jumlah',
        'kondisi',
        'hari_terlambat',
        'tarif_denda_snapshot',
        'total_denda',
        'catatan'
    ];

    protected $casts = [
        'tarif_denda_snapshot' => 'decimal:2',
        'total_denda' => 'decimal:2',
        'tanggal_kembali' => 'date',
    ];

    public function detailPeminjaman(): BelongsTo {
        return $this->belongsTo(DetailPeminjaman::class, 'detail_peminjaman_id');
    }

    public function petugas(): BelongsTo {
        return $this->belongsTo(User::class, 'petugas_id');
    }

}
