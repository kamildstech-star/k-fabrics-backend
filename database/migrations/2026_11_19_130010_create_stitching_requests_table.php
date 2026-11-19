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
        Schema::create('stitching_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('stitching_service_id')->nullable()->constrained('stitching_services')->nullOnDelete();
            $table->foreignId('measurement_id')->nullable()->constrained('customer_measurements')->nullOnDelete();
            $table->enum('request_type', ['custom_fitting', 'standalone_stitching'])->index();
            $table->enum('fabric_delivery_method', ['courier_by_customer', 'rider_pickup', 'shop_dropoff'])->nullable();
            $table->timestamp('fabric_received_at')->nullable();
            $table->string('design_reference_image')->nullable();
            $table->string('neck_style')->nullable();
            $table->string('sleeve_style')->nullable();
            $table->string('trouser_style')->nullable();
            $table->text('special_instructions')->nullable();
            $table->decimal('stitching_fee', 10, 2)->default(0);
            $table->enum('status', ['awaiting_fabric', 'cutting', 'stitching', 'finishing', 'ready'])->default('awaiting_fabric')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stitching_requests');
    }
};
