<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Alat extends Model
{
    use SoftDeletes;

    protected $table = 'alats';

    protected $fillable = [
        'kategori_id',
        'kode',
        'nama',
        'deskripsi',
        'foto',
        'stok_total',
        'stok_tersedia',
        'kondisi',
        'tarif_denda_per_hari',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tarif_denda_per_hari' => 'decimal:2',
    ];

    public function kategori(): BelongsTo {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    public function detailPeminjaman(): HasMany {
        return $this->hasMany(DetailPeminjaman::class);
    }

    public function scopeTersedia(Builer $query): void {
        $query->where('is_active', true)
            ->where('stok_tersedia', '>', 0);
    }
}
