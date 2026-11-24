<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class LogAktifitas extends Model
{
    const UPDATED_AT = null;

    protected $table = 'log_aktifitas';

    protected $fillable = [
        'user_id',
        'aksi',
        'modul',
        'data_id',
        'data_lama',
        'data_baru',
        'ip_address',
    ];

    protected $casts = [
        'data_lama' => 'json',
        'data_baru' => 'json',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function catat(
        string $aksi,
        string $modul,
        ?int $dataId = null,
        ?array $lama = null,
        ?array $baru = null,
    ): void {
        static::create([
            'user_id' => Auth::id(),
            'aksi'       => $aksi,
            'modul'      => $modul,
            'data_id'    => $dataId,
            'data_lama'  => $lama,
            'data_baru'  => $baru,
            'ip_address' => request()->ip(),
        ]);
    }
}
