<?php

use App\Http\Controllers\Api\KategoriController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PeminjamanController;
use App\Http\Controllers\Api\PengembalianController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\LogAktivitasController;
use App\Http\Controllers\Api\AlatController;
use App\Http\Controllers\Api\ProfileController;

// Guest
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:forgot-password');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:reset-password');

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:verify'])->name('verification.verify');

Route::middleware(['auth:sanctum', 'active'])->group(function () {

    // Semua role
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:verification');
    Route::put('/me', [ProfileController::class, 'update']);
    Route::put('/me/password', [ProfileController::class, 'password'])->middleware('throttle:password-change');

    Route::get('/kategori', [KategoriController::class, 'index']);
    Route::get('/alat', [AlatController::class, 'index']);
    Route::get('/alat/{alat}', [AlatController::class, 'show']);
    Route::get('/peminjaman', [PeminjamanController::class, 'index']);
    Route::get('/peminjaman/{peminjaman}', [PeminjamanController::class, 'show']);

    // Peminjam + admin (email wajib terverifikasi)
    Route::middleware(['role:peminjam,admin', 'verified'])->group(function () {
        Route::post('/peminjaman', [PeminjamanController::class, 'store']);
        Route::post('/peminjaman/{peminjaman}/cancel', [PeminjamanController::class, 'cancel']);
    });

    // Petugas + admin
    Route::middleware('role:petugas,admin')->group(function () {
        Route::post('/peminjaman/{peminjaman}/approve', [PeminjamanController::class, 'approve']);
        Route::post('/peminjaman/{peminjaman}/reject', [PeminjamanController::class, 'reject']);

        Route::get('/pengembalian', [PengembalianController::class, 'index']);
        Route::get('/pengembalian/{pengembalian}', [PengembalianController::class, 'show']);
        Route::post('/pengembalian', [PengembalianController::class, 'store']);

        Route::get('/laporan/peminjaman', [LaporanController::class, 'peminjaman']);
        Route::get('/laporan/pengembalian', [LaporanController::class, 'pengembalian']);
    });

    // Admin saja
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('kategori', KategoriController::class)->except(['index']);
        Route::apiResource('alat', AlatController::class)->except(['index', 'show']);
        Route::put('/peminjaman/{peminjaman}', [PeminjamanController::class, 'update']);
        Route::delete('/peminjaman/{peminjaman}', [PeminjamanController::class, 'destroy']);

        Route::get('/log-aktivitas', [LogAktivitasController::class, 'index']);
        Route::get('/log-aktivitas/{logAktivitas}', [LogAktivitasController::class, 'show']);
    });
});