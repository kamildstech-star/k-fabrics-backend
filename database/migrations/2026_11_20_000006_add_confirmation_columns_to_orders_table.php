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
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('customer_phone');
            $table->enum('confirmation_channel', ['none', 'whatsapp_bot', 'manual_call', 'advance_paid'])->default('none')->after('order_status');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_channel');
            $table->string('cancelled_reason')->nullable()->after('confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['phone_verified_at', 'confirmation_channel', 'confirmed_at', 'cancelled_reason']);
        });
    }
};
