<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Tool extends Model
{
    use SoftDeletes;

    /**
     * Kolom stok (total/available/damaged/maintenance/lost) SENGAJA tidak ada di sini.
     * Stok hanya berubah lewat stored procedure/service yang menulis stock_movements.
     * Untuk stok awal gunakan forceFill() di dalam service tersebut.
     */
    protected $fillable = [
        'category_id',
        'code',
        'name',
        'description',
        'photo_path',
        'is_active',
    ];

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'is_active'         => 'boolean',
            'total_stock'       => 'integer',
            'available_stock'   => 'integer',
            'damaged_stock'     => 'integer',
            'maintenance_stock' => 'integer',
            'lost_stock'        => 'integer',
            'borrowed_stock'    => 'integer', // generated column, read-only
        ];
    }

    // --- Relasi ---

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function loanDetails(): HasMany
    {
        return $this->hasMany(LoanDetail::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // --- Scope ---

    /** Alat yang tampil di katalog. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Alat yang bisa dipinjam sekarang. */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('available_stock', '>', 0);
    }

    // --- Accessor ---

    protected function photoUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null
        );
    }
}