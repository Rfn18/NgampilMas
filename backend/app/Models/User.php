<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'role',
        'username',
        'email',
        'password',
        'identity_number',
        'phone_number',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role'              => UserRole::class,
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'is_active'         => 'boolean',
            'password'          => 'hashed',
        ];
    }

    // --- Relasi ---

    /** Peminjaman yang diajukan user ini (sebagai borrower). */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'borrower_id');
    }

    /** Peminjaman yang disetujui/ditolak user ini (sebagai staff/admin). */
    public function reviewedLoans(): HasMany
    {
        return $this->hasMany(Loan::class, 'reviewed_by');
    }

    /** Pengembalian yang diterima user ini. */
    public function receivedReturns(): HasMany
    {
        return $this->hasMany(LoanReturn::class, 'received_by');
    }

    public function fineRates(): HasMany
    {
        return $this->hasMany(FineRate::class, 'created_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'performed_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // --- Scope ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithRole(Builder $query, UserRole ...$roles): Builder
    {
        return $query->whereIn('role', array_map(fn (UserRole $r) => $r->value, $roles));
    }

    // --- Helper role ---

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function isBorrower(): bool
    {
        return $this->role === UserRole::Borrower;
    }

    public function canReview(): bool
    {
        return $this->role->canReview();
    }
}