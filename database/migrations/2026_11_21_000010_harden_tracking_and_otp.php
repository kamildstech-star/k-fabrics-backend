<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_nonce', 64)->nullable();
        });
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->string('verification_token_hash', 64)->nullable()->unique();
            $table->timestamp('consumed_at')->nullable();
            $table->index(['phone', 'purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $table->dropUnique(['verification_token_hash']);
            $table->dropIndex(['phone', 'purpose', 'created_at']);
            $table->dropColumn(['verification_token_hash', 'consumed_at']);
        });
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('tracking_nonce'));
    }
};
