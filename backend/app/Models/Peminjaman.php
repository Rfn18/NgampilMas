<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Peminjaman extends Model
{
    protected $table = 'peminjaman';

    protected $fillable = [
        'kode', 'peminjam_id', 'petugas_id',
        'tanggal_pengajuan', 'tanggal_pinjam', 'tanggal_jatuh_tempo',
        'status', 'keperluan', 'catatan_petugas', 'disetujui_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pengajuan'   => 'date',
            'tanggal_pinjam'      => 'date',
            'tanggal_jatuh_tempo' => 'date',
            'disetujui_at'        => 'datetime',
        ];
    }

    public function peminjam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'peminjam_id');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailPeminjaman::class);
    }

    public function scopeTerlambat(Builder $query): void
    {
        $query->where('status', 'dipinjam')
              ->whereDate('tanggal_jatuh_tempo', '<', now());
    }

    protected function isTerlambat(): Attribute
    {
        return Attribute::get(
            fn () => $this->status === 'dipinjam'
                && $this->tanggal_jatuh_tempo->isPast()
        );
    }
}