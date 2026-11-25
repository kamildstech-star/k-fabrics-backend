<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \LogicException('Demo seeders may only run in local or testing environments.');
        }

        $user = User::where('email', 'ayesha@kfabrics.com')->first();
        $order = Order::where('order_number', 'KF-2026-0001')->first();

        $ivoryLawn = Product::where('slug', 'ivory-embroidered-lawn-suit')->first();
        if ($ivoryLawn) {
            ProductReview::firstOrCreate(
                [
                    'product_id' => $ivoryLawn->id,
                    'reviewer_name' => 'Ayesha Khan',
                ],
                [
                    'user_id' => $user?->id,
                    'order_id' => $order?->id,
                    'rating' => 5,
                    'comment' => 'The fabric quality exceeded my expectations! The embroidery on the neckline and sleeve cuffs is immaculate, and the chiffon dupatta is featherlight. Delivered in pristine packaging within 3 days to Lahore.',
                    'photo_url' => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=800&q=80',
                    'is_verified_purchase' => true,
                    'is_approved' => true,
                    'created_at' => now()->subDays(5),
                ]
            );

            ProductReview::firstOrCreate(
                [
                    'product_id' => $ivoryLawn->id,
                    'reviewer_name' => 'Zainab Tariq',
                ],
                [
                    'user_id' => null,
                    'order_id' => null,
                    'rating' => 5,
                    'comment' => 'Wore this for an outdoor brunch and received countless compliments! The bespoke stitching service made it fit like a glove. Highly recommended!',
                    'photo_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=800&q=80',
                    'is_verified_purchase' => true,
                    'is_approved' => true,
                    'created_at' => now()->subDays(12),
                ]
            );

            ProductReview::firstOrCreate(
                [
                    'product_id' => $ivoryLawn->id,
                    'reviewer_name' => 'Hira Mansoor',
                ],
                [
                    'user_id' => null,
                    'order_id' => null,
                    'rating' => 4,
                    'comment' => 'Beautiful luxury feel. Fabric breathes well in Karachi humidity. Trouser length was just right.',
                    'photo_url' => null,
                    'is_verified_purchase' => false,
                    'is_approved' => true,
                    'created_at' => now()->subDays(18),
                ]
            );
        }

        $terracotta = Product::where('slug', 'terracotta-raw-silk-co-ord')->first();
        if ($terracotta) {
            ProductReview::firstOrCreate(
                [
                    'product_id' => $terracotta->id,
                    'reviewer_name' => 'Mahnoor Bilal',
                ],
                [
                    'user_id' => null,
                    'order_id' => null,
                    'rating' => 5,
                    'comment' => 'The raw silk drape and color saturation are gorgeous. The subtle sheen gives it such a sophisticated look for evening dinners.',
                    'photo_url' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=800&q=80',
                    'is_verified_purchase' => true,
                    'is_approved' => true,
                    'created_at' => now()->subDays(8),
                ]
            );
        }

        $sageChiffon = Product::where('slug', 'sage-chiffon-formal-ensemble')->first();
        if ($sageChiffon) {
            ProductReview::firstOrCreate(
                [
                    'product_id' => $sageChiffon->id,
                    'reviewer_name' => 'Sana Mir',
                ],
                [
                    'user_id' => null,
                    'order_id' => null,
                    'rating' => 5,
                    'comment' => 'Stunning craftsmanship on the tilla work! Perfect for wedding guest attire.',
                    'photo_url' => null,
                    'is_verified_purchase' => true,
                    'is_approved' => true,
                    'created_at' => now()->subDays(15),
                ]
            );
        }
    }
}
