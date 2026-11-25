<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('idempotency_hash', 64)->nullable()->unique();
            $table->string('checkout_hash', 64)->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('advance_due_minor')->default(0);
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Null identifies historical lines for which reservation is inferred from the stored size.
            $table->unsignedInteger('reserved_quantity')->nullable();
        });

        Schema::table('stitching_requests', function (Blueprint $table) {
            $table->json('measurement_snapshot')->nullable();
        });

        // Freeze existing linked measurements and preserve the stated advance rule.
        DB::table('stitching_requests')->whereNotNull('measurement_id')->orderBy('id')->chunkById(100, function ($requests): void {
            $measurements = DB::table('customer_measurements')->whereIn('id', $requests->pluck('measurement_id'))->get()->keyBy('id');
            foreach ($requests as $request) {
                $measurement = $measurements->get($request->measurement_id);
                if ($measurement !== null) {
                    $snapshot = (array) $measurement;
                    unset($snapshot['id'], $snapshot['user_id'], $snapshot['created_at'], $snapshot['updated_at']);
                    DB::table('stitching_requests')->where('id', $request->id)->update([
                        'measurement_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                    ]);
                }
            }
        });
        DB::table('orders')->whereExists(function ($query): void {
            $query->selectRaw('1')->from('stitching_requests')->whereColumn('stitching_requests.order_id', 'orders.id');
        })->orderBy('id')->chunkById(100, function ($orders): void {
            foreach ($orders as $order) {
                $minor = (int) round((float) $order->total_amount * 100);
                DB::table('orders')->where('id', $order->id)->update(['advance_due_minor' => intdiv($minor + 1, 2)]);
            }
        });

        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 12);
            $table->unsignedBigInteger('amount_minor');
            $table->string('reference', 120)->unique();
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
        Schema::table('stitching_requests', fn (Blueprint $table) => $table->dropColumn('measurement_snapshot'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('reserved_quantity'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropUnique(['idempotency_hash']);
            $table->dropColumn(['idempotency_hash', 'checkout_hash', 'advance_due_minor']);
        });
    }
};
