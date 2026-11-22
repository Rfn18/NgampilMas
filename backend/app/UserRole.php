<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin    = 'admin';
    case Staff    = 'staff';
    case Borrower = 'borrower';

    public function label(): string
    {
        return match ($this) {
            self::Admin    => 'Administrator',
            self::Staff    => 'Staff / Petugas',
            self::Borrower => 'Borrower / Peminjam',
        };
    }

    /** Role yang boleh menyetujui/menolak peminjaman dan memproses pengembalian. */
    public function canReview(): bool
    {
        return in_array($this, [self::Admin, self::Staff], true);
    }

    /** Nilai string untuk validation rule: Rule::in(UserRole::values()). */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}