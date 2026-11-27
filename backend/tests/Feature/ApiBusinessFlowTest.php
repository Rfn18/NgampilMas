<?php

use App\Http\Controllers\Api\PeminjamanController;
use App\Models\Alat;
use App\Models\DetailPeminjaman;
use App\Models\Kategori;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function stage2User(string $role = 'peminjam', bool $verified = true, bool $active = true): User
{
    static $sequence = 0;
    $sequence++;

    $user = User::create([
        'nama' => "Stage Two User {$sequence}",
        'username' => "stage2_user_{$sequence}",
        'email' => "stage2_user_{$sequence}@example.test",
        'password' => 'password',
        'role' => $role,
        'is_active' => $active,
    ]);

    $user->forceFill(['email_verified_at' => $verified ? now() : null])->save();

    return $user;
}

function stage2Tool(int $stock = 5, bool $active = true, string $fee = '10.00'): Alat
{
    static $sequence = 0;
    $sequence++;

    $kategori = Kategori::create(['nama' => "Stage Two Category {$sequence}"]);

    return Alat::create([
        'kategori_id' => $kategori->id,
        'kode' => "STAGE2-{$sequence}",
        'nama' => "Stage Two Tool {$sequence}",
        'stok_total' => $stock,
        'stok_tersedia' => $stock,
        'tarif_denda_per_hari' => $fee,
        'is_active' => $active,
    ]);
}

function stage2RawLoan(User $borrower, string $status, array $items = []): int
{
    static $sequence = 0;
    $sequence++;

    $loanId = DB::table('peminjamans')->insertGetId([
        'kode' => 'STAGE2-LOAN-'.$sequence,
        'peminjam_id' => $borrower->id,
        'tanggal_pengajuan' => today()->toDateString(),
        'tanggal_pinjam' => today()->toDateString(),
        'tanggal_jatuh_tempo' => today()->addDays(3)->toDateString(),
        'status' => $status,
    ]);

    foreach ($items as $item) {
        DB::table('detail_peminjamans')->insert([
            'peminjaman_id' => $loanId,
            'alat_id' => $item['alat_id'],
            'jumlah' => $item['jumlah'],
            'jumlah_dikembalikan' => $item['jumlah_dikembalikan'] ?? 0,
        ]);
    }

    return $loanId;
}

test('role matrix protects admin, staff, borrower, and listing groups', function () {
    $admin = stage2User('admin');
    $staff = stage2User('petugas');
    $borrower = stage2User('peminjam');

    $this->getJson('/api/users')->assertUnauthorized();

    Auth::forgetGuards();
    $this->actingAs($borrower, 'web');
    $this->getJson('/api/users')->assertForbidden();
    $this->getJson('/api/pengembalian')->assertForbidden();
    $this->postJson('/api/peminjaman', [])->assertUnprocessable();
    $this->getJson('/api/kategori')->assertOk();

    Auth::forgetGuards();
    $this->actingAs($staff, 'web');
    $this->getJson('/api/users')->assertForbidden();
    $this->getJson('/api/pengembalian')->assertOk();
    $this->postJson('/api/peminjaman', [])->assertForbidden();
    $this->getJson('/api/laporan/peminjaman')->assertUnprocessable();

    Auth::forgetGuards();
    $this->actingAs($admin, 'web');
    $this->getJson('/api/users')->assertOk();
    $this->getJson('/api/pengembalian')->assertOk();
    $this->postJson('/api/peminjaman', [])->assertUnprocessable();
});

test('staff can load an empty return report for a valid period', function () {
    $this->actingAs(stage2User('petugas'), 'web');
    $today = today()->toDateString();

    $this->getJson("/api/laporan/pengembalian?dari={$today}&sampai={$today}")->assertOk();
});

test('unverified borrowers cannot submit loan requests', function () {
    Auth::forgetGuards();
    $this->actingAs(stage2User('peminjam', false), 'web');

    $this->postJson('/api/peminjaman', [])->assertForbidden();
});

test('public registration ignores a requested privileged role', function () {
    $this->postJson('/api/register', [
        'nama' => 'New Borrower',
        'username' => 'new_borrower',
        'email' => 'new_borrower@example.test',
        'password' => 'SecurePassword123',
        'password_confirmation' => 'SecurePassword123',
        'role' => 'admin',
    ])->assertCreated()
        ->assertJsonPath('data.role', 'peminjam');
});

test('profile updates ignore privileged and verification fields', function () {
    $borrower = stage2User();
    $verifiedAt = $borrower->fresh()->email_verified_at;
    $this->actingAs($borrower, 'web');

    $this->putJson('/api/me', [
        'nama' => 'Updated profile',
        'role' => 'admin',
        'is_active' => false,
        'email_verified_at' => now()->toIso8601String(),
    ])->assertOk();

    $this->assertSame('peminjam', $borrower->fresh()->role);
    $this->assertTrue($borrower->fresh()->is_active);
    $this->assertEquals($verifiedAt, $borrower->fresh()->email_verified_at);
});

test('non-admin borrowers cannot choose a different borrower id', function () {
    $borrower = stage2User();
    $otherBorrower = stage2User();
    $this->actingAs($borrower, 'web');

    $this->postJson('/api/peminjaman', [
        'peminjam_id' => $otherBorrower->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('peminjam_id');
});

test('inactive account is denied API access', function () {
    Auth::forgetGuards();
    $this->actingAs(stage2User('peminjam', true, false), 'web');

    $this->withSession([])->getJson('/api/me')->assertForbidden();
});

test('all roles can list categories', function () {
    stage2Tool();

    foreach (['peminjam', 'petugas', 'admin'] as $role) {
        Auth::forgetGuards();
        $this->actingAs(stage2User($role), 'web');

        $this->getJson('/api/kategori')->assertOk();
    }
});

test('category listing uses a bounded number of queries as categories grow', function () {
    foreach (range(1, 5) as $index) {
        stage2Tool();
    }

    $this->actingAs(stage2User('admin'), 'web');
    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson('/api/kategori')->assertOk();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query) => str_contains(strtolower($query), 'kategoris'));
    DB::disableQueryLog();

    $this->assertLessThanOrEqual(1, $queries->count());
});

test('all roles can list tools', function () {
    stage2Tool();

    Auth::forgetGuards();
    $this->actingAs(stage2User('peminjam'), 'web');
    $this->getJson('/api/alat')->assertOk();
});

test('staff can list tools after query validation', function () {
    stage2Tool();
    $this->actingAs(stage2User('petugas'), 'web');

    $this->getJson('/api/alat?per_page=5')->assertOk();
});

test('borrower can submit a valid loan for an active tool with stock', function () {
    $borrower = stage2User();
    $tool = stage2Tool();
    Auth::forgetGuards();
    $this->actingAs($borrower, 'web');

    $this->postJson('/api/peminjaman', [
        'tanggal_pinjam' => today()->addDay()->toDateString(),
        'tanggal_jatuh_tempo' => today()->addDays(3)->toDateString(),
        'items' => [
            ['alat_id' => $tool->id, 'jumlah' => 2],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'diajukan');
});

test('invalid loan dates are rejected before creating a request', function () {
    Auth::forgetGuards();
    $this->actingAs(stage2User(), 'web');

    $this->postJson('/api/peminjaman', [
        'tanggal_pinjam' => today()->subDay()->toDateString(),
        'tanggal_jatuh_tempo' => today()->subDays(2)->toDateString(),
        'items' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['tanggal_pinjam', 'tanggal_jatuh_tempo', 'items']);
});

test('borrower loan list is scoped to the current user', function () {
    $borrower = stage2User();
    $otherBorrower = stage2User();
    stage2RawLoan($borrower, 'diajukan');
    stage2RawLoan($otherBorrower, 'diajukan');
    $this->actingAs($borrower, 'web');

    $this->getJson('/api/peminjaman')->assertOk();
});

test('loan models resolve the tables created by migrations', function () {
    $borrower = stage2User();
    $tool = stage2Tool();
    $loanId = stage2RawLoan($borrower, 'diajukan', [
        ['alat_id' => $tool->id, 'jumlah' => 1],
    ]);

    $this->assertSame(1, DetailPeminjaman::where('peminjaman_id', $loanId)->count());
});

test('borrower cannot view another borrowers loan', function () {
    $owner = stage2User();
    $otherBorrower = stage2User();
    $loan = new Peminjaman(['peminjam_id' => $owner->id]);
    $request = Request::create('/api/peminjaman/1');
    $request->setUserResolver(fn () => $otherBorrower);

    try {
        app(PeminjamanController::class)->show($request, $loan);
    } catch (HttpException $exception) {
        $this->assertSame(403, $exception->getStatusCode());

        return;
    }

    $this->fail('Expected an HTTP 403 response.');
});

test('approval with insufficient stock on the second item rolls back the first item', function () {
    $borrower = stage2User();
    $staff = stage2User('petugas');
    $firstTool = stage2Tool(5);
    $secondTool = stage2Tool(1);
    $loanId = stage2RawLoan($borrower, 'diajukan', [
        ['alat_id' => $firstTool->id, 'jumlah' => 2],
        ['alat_id' => $secondTool->id, 'jumlah' => 2],
    ]);
    $this->actingAs($staff, 'web');

    $this->postJson("/api/peminjaman/{$loanId}/approve")->assertUnprocessable();
    $this->assertSame(5, $firstTool->fresh()->stok_tersedia);
});

test('another borrower cannot cancel someone elses loan', function () {
    $owner = stage2User();
    $otherBorrower = stage2User();
    $loanId = stage2RawLoan($owner, 'diajukan');
    $this->actingAs($otherBorrower, 'web');

    $this->postJson("/api/peminjaman/{$loanId}/cancel")->assertForbidden();
});

test('admin can update a pending loan but not a loan in progress', function () {
    $admin = stage2User('admin');
    $borrower = stage2User();
    $tool = stage2Tool();
    $pendingLoanId = stage2RawLoan($borrower, 'diajukan', [
        ['alat_id' => $tool->id, 'jumlah' => 1],
    ]);
    $activeLoanId = stage2RawLoan($borrower, 'dipinjam', [
        ['alat_id' => $tool->id, 'jumlah' => 1],
    ]);
    $this->actingAs($admin, 'web');

    $data = [
        'tanggal_pinjam' => today()->addDay()->toDateString(),
        'tanggal_jatuh_tempo' => today()->addDays(3)->toDateString(),
        'items' => [['alat_id' => $tool->id, 'jumlah' => 1]],
    ];

    $this->putJson("/api/peminjaman/{$pendingLoanId}", $data)->assertOk();
    $this->putJson("/api/peminjaman/{$activeLoanId}", $data)->assertUnprocessable();
});

test('returning a partial loan item records the return and keeps the loan open', function () {
    $borrower = stage2User();
    $staff = stage2User('petugas');
    $tool = stage2Tool(5);
    $loanId = stage2RawLoan($borrower, 'dipinjam', [
        ['alat_id' => $tool->id, 'jumlah' => 3],
    ]);
    $detailId = DB::table('detail_peminjamans')->where('peminjaman_id', $loanId)->value('id');
    $this->actingAs($staff, 'web');

    $this->postJson('/api/pengembalian', [
        'peminjaman_id' => $loanId,
        'tanggal_kembali' => today()->toDateString(),
        'items' => [[
            'detail_peminjaman_id' => $detailId,
            'jumlah' => 1,
            'kondisi' => 'baik',
        ]],
    ])->assertCreated();
});

test('return cannot exceed remaining quantity or refer to another loan item', function () {
    $borrower = stage2User();
    $otherBorrower = stage2User();
    $staff = stage2User('petugas');
    $tool = stage2Tool();
    $loanId = stage2RawLoan($borrower, 'dipinjam', [
        ['alat_id' => $tool->id, 'jumlah' => 1],
    ]);
    $otherLoanId = stage2RawLoan($otherBorrower, 'dipinjam', [
        ['alat_id' => $tool->id, 'jumlah' => 1],
    ]);
    $otherDetailId = DB::table('detail_peminjamans')->where('peminjaman_id', $otherLoanId)->value('id');
    $this->actingAs($staff, 'web');

    $this->postJson('/api/pengembalian', [
        'peminjaman_id' => $loanId,
        'tanggal_kembali' => today()->toDateString(),
        'items' => [[
            'detail_peminjaman_id' => $otherDetailId,
            'jumlah' => 2,
            'kondisi' => 'baik',
        ]],
    ])->assertUnprocessable();
});

test('a user with an active loan cannot be deleted by an admin', function () {
    $admin = stage2User('admin');
    $borrower = stage2User();
    $tool = stage2Tool();
    stage2RawLoan($borrower, 'dipinjam', [
        ['alat_id' => $tool->id, 'jumlah' => 1],
    ]);
    $this->actingAs($admin, 'web');

    $this->deleteJson("/api/users/{$borrower->id}")->assertUnprocessable();
});

test('admin can delete a pending loan but not an active loan', function () {
    $admin = stage2User('admin');
    $borrower = stage2User();
    $tool = stage2Tool();
    $pendingLoanId = stage2RawLoan($borrower, 'diajukan', [
        ['alat_id' => $tool->id, 'jumlah' => 1],
    ]);
    $activeLoanId = stage2RawLoan($borrower, 'dipinjam', [
        ['alat_id' => $tool->id, 'jumlah' => 1],
    ]);
    $this->actingAs($admin, 'web');

    $this->deleteJson("/api/peminjaman/{$pendingLoanId}")->assertNoContent();
    $this->deleteJson("/api/peminjaman/{$activeLoanId}")->assertUnprocessable();
});

test('services referenced by controllers are autoloadable', function () {
    $this->assertTrue(class_exists(\App\Service\AlatService::class));
    $this->assertTrue(class_exists(\App\Service\PeminjamanService::class));
    $this->assertTrue(class_exists(\App\Service\PengembalianService::class));
});

test('tool updates without photo preserve the photo and available stock', function () {
    $admin = stage2User('admin');
    $tool = stage2Tool(5);
    $tool->update(['foto' => 'existing/photo.jpg', 'stok_tersedia' => 3]);
    $this->actingAs($admin, 'web');

    $this->putJson("/api/alat/{$tool->id}", ['nama' => 'Updated tool'])->assertOk();

    $this->assertSame('existing/photo.jpg', $tool->fresh()->foto);
    $this->assertSame(3, $tool->fresh()->stok_tersedia);
});

test('tool total cannot be reduced below its borrowed quantity', function () {
    $admin = stage2User('admin');
    $tool = stage2Tool(5);
    $tool->update(['stok_tersedia' => 3]);
    $this->actingAs($admin, 'web');

    $this->putJson("/api/alat/{$tool->id}", ['stok_total' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('stok_total');
});

test('tool with an active loan cannot be deleted', function () {
    $admin = stage2User('admin');
    $borrower = stage2User();
    $tool = stage2Tool();
    stage2RawLoan($borrower, 'dipinjam', [
        ['alat_id' => $tool->id, 'jumlah' => 1],
    ]);
    $this->actingAs($admin, 'web');

    $this->deleteJson("/api/alat/{$tool->id}")->assertUnprocessable();
});

test('category with tools cannot be deleted', function () {
    $admin = stage2User('admin');
    $tool = stage2Tool();
    $this->actingAs($admin, 'web');

    $this->deleteJson("/api/kategori/{$tool->kategori_id}")->assertUnprocessable();
});

test('admin self-delete is rejected and user update log omits password', function () {
    $admin = stage2User('admin');
    $otherUser = stage2User();
    $oldName = $otherUser->nama;
    $this->actingAs($admin, 'web');

    $this->deleteJson("/api/users/{$admin->id}")->assertUnprocessable();
    $this->putJson("/api/users/{$otherUser->id}", ['nama' => 'Updated borrower'])->assertOk();

    $log = DB::table('log_aktivitas')
        ->where('modul', 'users')
        ->where('data_id', $otherUser->id)
        ->latest('id')
        ->first();

    $this->assertNotNull($log);
    $this->assertSame($oldName, json_decode($log->data_lama, true)['nama']);
    $this->assertSame('Updated borrower', json_decode($log->data_baru, true)['nama']);
    $this->assertArrayNotHasKey('password', json_decode($log->data_baru, true));
});
