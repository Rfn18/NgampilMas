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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_id')->constrained('tools')->onDelete('cascade');
            $table->enum('movement_type', ['initial_stock', 'loan_out', 'loan_return', 'condition_change', 'adjustment'])->default('initial_stock');
            $table->enum('from_bucket', ['external', 'available', 'borrowed', 'damaged', 'maintenance', 'lost'])->default('available');
            $table->enum('to_bucket', ['external', 'available', 'borrowed', 'damaged', 'maintenance', 'lost'])->default('available');
            $table->integer('quantity')->default(0);
            $table->foreignId('loan_id')->constrained('loans')->nullable()->onDelete('cascade');
            $table->foreignId('restoration_id')->constrained('restorations')->nullable()->onDelete('cascade');
            $table->foreignId('performed_by')->constrained('users')->onDelete('cascade');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
