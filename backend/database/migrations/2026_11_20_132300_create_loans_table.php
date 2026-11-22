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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_code')->unique();
            $table->foreignId('borrower_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewed_by')->constrained('users')->onDelete('cascade');
            $table->string('review_note')->nullable();
            $table->foreignId('fine_rate_id')->constrained('fine_rates')->onDelete('cascade');
            $table->decimal('fine_rate_per_day', 10, 2);
            $table->dateTime('requested_at');
            $table->date('loan_date');
            $table->date('due_date');
            $table->text('purpose');
            $table->enum('status', ['pending', 'rejected', 'cancelled', 'borrowed', 'partially_returned', 'returned'])->default('pending');
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
