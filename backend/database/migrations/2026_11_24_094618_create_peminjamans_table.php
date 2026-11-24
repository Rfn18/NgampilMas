<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('peminjamans', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->foreignId('peminjam_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('petugas_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('tanggal_pengajuan');
            $table->date('tanggal_pinjam');
            $table->date('tanggal_jatuh_tempo');
            $table->enum('status', [
                'diajukan',
                'disetujui',
                'ditolak',
                'dipinjam',
                'selesai',
                'dibatalkan',
            ])->default('diajukan');
            $table->text('keperluan')->nullable();
            $table->text('catatan_petugas')->nullable();
            $table->timestamp('disetujui_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'tanggal_jatuh_tempo']);
            $table->index('peminjam_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peminjams');
    }
};
