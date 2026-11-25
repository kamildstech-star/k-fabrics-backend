<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // A null deadline leaves existing orders outside automatic expiry.
            $table->timestamp('cod_confirmation_expires_at')->nullable();
            $table->index(['payment_method', 'order_status', 'cod_confirmation_expires_at'], 'orders_cod_hold_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_cod_hold_due_idx');
            $table->dropColumn('cod_confirmation_expires_at');
        });
    }
};
