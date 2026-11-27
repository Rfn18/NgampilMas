<?php

use App\Models\Alat;
use App\Models\Kategori;
use App\Models\Peminjaman;
use App\Models\User;
use App\Service\PengembalianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('return service records overdue partial returns and preserves stock invariants', function () {
    DB::statement('CREATE TEMPORARY TABLE peminjaman (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kode VARCHAR(30) NOT NULL,
        peminjam_id BIGINT UNSIGNED NOT NULL,
        petugas_id BIGINT UNSIGNED NULL,
        tanggal_pengajuan DATE NOT NULL,
        tanggal_pinjam DATE NOT NULL,
        tanggal_jatuh_tempo DATE NOT NULL,
        status VARCHAR(30) NOT NULL,
        keperluan TEXT NULL,
        catatan_petugas TEXT NULL,
        disetujui_at TIMESTAMP NULL,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL
    ) ENGINE=InnoDB');
    DB::statement('CREATE TEMPORARY TABLE detail_peminjaman (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        peminjaman_id BIGINT UNSIGNED NOT NULL,
        alat_id BIGINT UNSIGNED NOT NULL,
        jumlah INT UNSIGNED NOT NULL,
        jumlah_dikembalikan INT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB');

    $borrower = User::create([
        'nama' => 'Return borrower',
        'username' => 'return_borrower',
        'email' => 'return_borrower@example.test',
        'password' => 'password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);
    $staff = User::create([
        'nama' => 'Return staff',
        'username' => 'return_staff',
        'email' => 'return_staff@example.test',
        'password' => 'password',
        'role' => 'petugas',
        'is_active' => true,
    ]);
    $category = Kategori::create(['nama' => 'Return category']);
    $tool = Alat::create([
        'kategori_id' => $category->id,
        'kode' => 'RETURN-001',
        'nama' => 'Return tool',
        'stok_total' => 5,
        'stok_tersedia' => 3,
        'tarif_denda_per_hari' => 10,
        'is_active' => true,
    ]);

    $loanId = DB::table('peminjamans')->insertGetId([
        'kode' => 'RETURN-LOAN-001',
        'peminjam_id' => $borrower->id,
        'tanggal_pengajuan' => today()->subDays(5)->toDateString(),
        'tanggal_pinjam' => today()->subDays(5)->toDateString(),
        'tanggal_jatuh_tempo' => today()->subDays(2)->toDateString(),
        'status' => 'dipinjam',
    ]);
    $detailId = DB::table('detail_peminjamans')->insertGetId([
        'peminjaman_id' => $loanId,
        'alat_id' => $tool->id,
        'jumlah' => 2,
        'jumlah_dikembalikan' => 0,
    ]);

    DB::table('peminjaman')->insert([
        'id' => $loanId,
        'kode' => 'RETURN-LOAN-001',
        'peminjam_id' => $borrower->id,
        'tanggal_pengajuan' => today()->subDays(5)->toDateString(),
        'tanggal_pinjam' => today()->subDays(5)->toDateString(),
        'tanggal_jatuh_tempo' => today()->subDays(2)->toDateString(),
        'status' => 'dipinjam',
    ]);
    DB::table('detail_peminjaman')->insert([
        'id' => $detailId,
        'peminjaman_id' => $loanId,
        'alat_id' => $tool->id,
        'jumlah' => 2,
        'jumlah_dikembalikan' => 0,
    ]);

    $service = new PengembalianService();
    $loan = Peminjaman::findOrFail($loanId);

    expect(fn () => $service->catat($loan, today()->subDays(6)->toDateString(), [], $staff))
        ->toThrow(ValidationException::class);
    expect(fn () => $service->catat($loan, today()->toDateString(), [[
        'detail_peminjaman_id' => $detailId + 1000,
        'jumlah' => 1,
        'kondisi' => 'baik',
    ]], $staff))->toThrow(ValidationException::class);
    expect(fn () => $service->catat($loan, today()->toDateString(), [[
        'detail_peminjaman_id' => $detailId,
        'jumlah' => 3,
        'kondisi' => 'baik',
    ]], $staff))->toThrow(ValidationException::class);

    $firstReturn = $service->catat($loan, today()->toDateString(), [[
        'detail_peminjaman_id' => $detailId,
        'jumlah' => 1,
        'kondisi' => 'rusak_berat',
    ]], $staff);

    $this->assertSame('dipinjam', DB::table('peminjaman')->where('id', $loanId)->value('status'));
    $this->assertSame(4, $tool->fresh()->stok_total);
    $this->assertSame(3, $tool->fresh()->stok_tersedia);
    $this->assertSame(2, (int) $firstReturn->first()->hari_terlambat);
    $this->assertSame('10.00', $firstReturn->first()->tarif_denda_snapshot);
    $this->assertSame('20.00', $firstReturn->first()->total_denda);

    $service->catat($loan, today()->toDateString(), [[
        'detail_peminjaman_id' => $detailId,
        'jumlah' => 1,
        'kondisi' => 'baik',
    ]], $staff);

    $this->assertSame('selesai', DB::table('peminjaman')->where('id', $loanId)->value('status'));
    $this->assertSame(2, (int) DB::table('detail_peminjaman')->where('id', $detailId)->value('jumlah_dikembalikan'));
    $this->assertSame(4, $tool->fresh()->stok_total);
    $this->assertSame(4, $tool->fresh()->stok_tersedia);
});
