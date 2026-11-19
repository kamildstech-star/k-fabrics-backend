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
        Schema::create('customer_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('profile_name')->nullable();
            $table->enum('unit', ['inches', 'cm'])->default('inches');
            $table->decimal('shirt_length', 5, 2)->nullable();
            $table->decimal('shoulder', 5, 2)->nullable();
            $table->decimal('chest', 5, 2)->nullable();
            $table->decimal('waist', 5, 2)->nullable();
            $table->decimal('hips', 5, 2)->nullable();
            $table->decimal('sleeve_length', 5, 2)->nullable();
            $table->decimal('armhole', 5, 2)->nullable();
            $table->decimal('daman', 5, 2)->nullable();
            $table->decimal('neck_front', 5, 2)->nullable();
            $table->decimal('neck_back', 5, 2)->nullable();
            $table->decimal('trouser_length', 5, 2)->nullable();
            $table->decimal('trouser_waist', 5, 2)->nullable();
            $table->decimal('pancha', 5, 2)->nullable();
            $table->text('extra_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_measurements');
    }
};
