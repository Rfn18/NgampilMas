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
        Schema::create('pengembalians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detail_peminjaman_id')->constrained('detail_peminjamans')->restrictOnDelete();
            $table->foreignId('petugas_id')->constrained('users')->restrictOnDelete();
            $table->date('tanggal_kembali');
            $table->unsignedInteger('jumlah');
            $table->enum('kondisi', ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'])->default('baik');
            $table->unsignedInteger('hari_terlambat')->default(0);
            $table->decimal('tarif_denda_snapshot', 10, 2)->default(0);
            $table->decimal('total_denda', 12, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index('tanggal_kembali');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengembalians');
    }
};
