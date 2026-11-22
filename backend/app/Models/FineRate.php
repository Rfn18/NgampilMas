<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class FineRate extends Model
{
    protected $fillable = [
        'rate_per_day',
        'effective_from',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rate_per_day'   => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Tarif yang sudah dipakai peminjaman tidak boleh diubah/dihapus.
        // Perubahan tarif = tambah baris baru dengan effective_from baru.
        static::updating(function (FineRate $rate) {
            if ($rate->loans()->exists()) {
                throw new LogicException('Tarif denda sudah dipakai transaksi dan tidak boleh diubah.');
            }
        });

        static::deleting(function (FineRate $rate) {
            if ($rate->loans()->exists()) {
                throw new LogicException('Tarif denda sudah dipakai transaksi dan tidak boleh dihapus.');
            }
        });
    }

    // --- Relasi ---

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    // --- Helper ---

    /** Tarif yang berlaku pada tanggal tertentu (effective_from terbesar yang <= tanggal). */
    public static function activeFor(CarbonInterface|string $date): ?self
    {
        return static::query()
            ->where('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();
    }
}