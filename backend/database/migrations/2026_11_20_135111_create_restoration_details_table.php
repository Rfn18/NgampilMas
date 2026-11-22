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
        Schema::create('restoration_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restoration_id')->constrained('restorations')->onDelete('cascade');
            $table->foreignId('loan_detail_id')->constrained('loan_details')->onDelete('cascade');
            $table->integer('quantity_returned')->default(0);
            $table->enum('item_condition', ['good', 'maintenance', 'damaged', 'lost'])->default('good');
            $table->string('damage_notes')->nullable();
            $table->integer('late_days')->default(0);
            $table->decimal('fine_amount', 12, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restoration_details');
    }
};
