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
        Schema::create('restorations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->onDelete('cascade');
            $table->enum('status', ['requested', 'verified', 'cancelled', 'rejected'])->default('requested');
            $table->foreignId('received_by')->constrained('users')->onDelete('cascade');
            $table->string('borrower_note')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->string('staff_note')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->boolean('open_flag')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restorations');
    }
};
