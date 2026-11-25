<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerMeasurement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StitchingRequest;
use App\Models\StitchingService;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \LogicException('Demo seeders may only run in local or testing environments.');
        }

        // 1. Admin & Customer Users
        $admin = User::firstOrCreate(
            ['email' => 'admin@kfabrics.com'],
            [
                'name' => 'K. Fabrics Admin',
                'phone' => '+923001234567',
                'role' => 'admin',
                'password' => Hash::make(Str::random(40)),
            ]
        );

        $customer = User::firstOrCreate(
            ['email' => 'ayesha@kfabrics.com'],
            [
                'name' => 'Ayesha Khan',
                'phone' => '+923211234567',
                'role' => 'customer',
                'password' => Hash::make(Str::random(40)),
            ]
        );

        // 2. Categories
        $dailyPret = Category::firstOrCreate(
            ['slug' => 'daily-pret'],
            [
                'name' => 'Daily Pret',
                'image_url' => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );

        Category::firstOrCreate(
            ['slug' => 'printed-kurtis'],
            [
                'name' => 'Printed Kurtis',
                'parent_id' => $dailyPret->id,
                'image_url' => 'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );

        $coordSets = Category::firstOrCreate(
            ['slug' => 'co-ord-sets'],
            [
                'name' => 'Co-ord Sets',
                'image_url' => 'https://images.unsplash.com/photo-1594938298603-c8148c4dae35?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );

        Category::firstOrCreate(
            ['slug' => 'solid-monochromes'],
            [
                'name' => 'Solid Monochromes',
                'parent_id' => $coordSets->id,
                'image_url' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );

        $festiveEdit = Category::firstOrCreate(
            ['slug' => 'festive-edit'],
            [
                'name' => 'Festive Edit',
                'image_url' => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );

        Category::firstOrCreate(
            ['slug' => 'embroidered-formals'],
            [
                'name' => 'Embroidered Formals',
                'parent_id' => $festiveEdit->id,
                'image_url' => 'https://images.unsplash.com/photo-1609357605129-26f69add5d6e?auto=format&fit=crop&w=800&q=80',
                'is_active' => true,
            ]
        );

        // 3. Stitching Services
        $simple2Piece = StitchingService::firstOrCreate(
            ['name' => 'Simple 2-Piece'],
            [
                'description' => 'Precision stitching for 2-piece suits with choice of round, boat, or V-neck and straight cut trousers.',
                'base_price' => 1500.00,
                'estimated_days' => 5,
                'is_active' => true,
            ]
        );

        $threePieceFinishing = StitchingService::firstOrCreate(
            ['name' => '3-Piece with Dupatta Finishing'],
            [
                'description' => 'Complete 3-piece tailoring with piping details, neckline lace placement, and complete dupatta border/pico finishing.',
                'base_price' => 2200.00,
                'estimated_days' => 7,
                'is_active' => true,
            ]
        );

        $fancyLiningSuit = StitchingService::firstOrCreate(
            ['name' => 'Fancy / Lining Suit'],
            [
                'description' => 'Luxury tailored craftsmanship featuring attached soft lining, custom sleeve styles, hand-tacking, and styled trousers.',
                'base_price' => 3500.00,
                'estimated_days' => 10,
                'is_active' => true,
            ]
        );

        // 4. Products & Variants & Images
        $productsData = [
            [
                'category_id' => $dailyPret->id,
                'name' => 'Ivory Embroidered Lawn Suit',
                'slug' => 'ivory-embroidered-lawn-suit',
                'description' => 'A timeless 3-piece luxury lawn silhouette featuring intricate floral tone-on-tone embroidery, delicate organza cutwork on the daman, and an airy chiffon dupatta.',
                'fabric_type' => 'Lawn',
                'piece_type' => '3-piece',
                'base_price' => 8500.00,
                'sale_price' => 7200.00,
                'is_featured' => true,
                'status' => 'active',
                'color' => 'Ivory',
                'color_hex' => '#FFFFF0',
                'images' => [
                    'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?auto=format&fit=crop&w=1000&q=80',
                ],
                'sizes' => ['XS', 'S', 'M', 'L', 'XL', 'Custom'],
            ],
            [
                'category_id' => $coordSets->id,
                'name' => 'Terracotta Pure Linen Co-ord',
                'slug' => 'terracotta-pure-linen-co-ord',
                'description' => 'Minimalist modern silhouette crafted from breathable organic linen. Features a relaxed collar, concealed placket, and tailored culottes.',
                'fabric_type' => 'Linen',
                'piece_type' => '2-piece',
                'base_price' => 6200.00,
                'sale_price' => null,
                'is_featured' => true,
                'status' => 'active',
                'color' => 'Terracotta',
                'color_hex' => '#E2725B',
                'images' => [
                    'https://images.unsplash.com/photo-1594938298603-c8148c4dae35?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=1000&q=80',
                ],
                'sizes' => ['S', 'M', 'L', 'XL', 'Custom'],
            ],
            [
                'category_id' => $festiveEdit->id,
                'name' => 'Emerald Green Organza Ensemble',
                'slug' => 'emerald-green-organza-ensemble',
                'description' => 'Opulent pure organza kurta paired with a raw silk undershirt and flared trousers. Hand-embellished zari work adorns the neckline and cuffs.',
                'fabric_type' => 'Organza',
                'piece_type' => '3-piece',
                'base_price' => 16500.00,
                'sale_price' => 14500.00,
                'is_featured' => true,
                'status' => 'active',
                'color' => 'Emerald Green',
                'color_hex' => '#1B4D3E',
                'images' => [
                    'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1609357605129-26f69add5d6e?auto=format&fit=crop&w=1000&q=80',
                ],
                'sizes' => ['S', 'M', 'L', 'Custom'],
            ],
            [
                'category_id' => $dailyPret->id,
                'name' => 'Classic Monochrome Cotton Kurti',
                'slug' => 'classic-monochrome-cotton-kurti',
                'description' => 'A sharp, understated everyday staple cut from premium combed Egyptian cotton with subtle stitch accents along the sleeves.',
                'fabric_type' => 'Cotton',
                'piece_type' => '1-piece',
                'base_price' => 3200.00,
                'sale_price' => 2800.00,
                'is_featured' => false,
                'status' => 'active',
                'color' => 'Black & White',
                'color_hex' => '#1A1A1A',
                'images' => [
                    'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=1000&q=80',
                ],
                'sizes' => ['XS', 'S', 'M', 'L'],
            ],
            [
                'category_id' => $coordSets->id,
                'name' => 'Sage Serenity Raw Silk Co-ord',
                'slug' => 'sage-serenity-raw-silk-co-ord',
                'description' => 'Pure Korean raw silk set in a soothing sage green tone. Features a boxy contemporary tunic and straight ankle-length pants.',
                'fabric_type' => 'Linen',
                'piece_type' => '2-piece',
                'base_price' => 9800.00,
                'sale_price' => null,
                'is_featured' => true,
                'status' => 'active',
                'color' => 'Sage Green',
                'color_hex' => '#9DC183',
                'images' => [
                    'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1594938298603-c8148c4dae35?auto=format&fit=crop&w=1000&q=80',
                ],
                'sizes' => ['S', 'M', 'L', 'XL'],
            ],
            [
                'category_id' => $festiveEdit->id,
                'name' => 'Blush Rose Embroidered Chiffon',
                'slug' => 'blush-rose-embroidered-chiffon',
                'description' => 'An ethereal festive ensemble in pale blush pink, embellished with micro-pearls and silver sequins over a feather-light chiffon base.',
                'fabric_type' => 'Organza',
                'piece_type' => '3-piece',
                'base_price' => 18900.00,
                'sale_price' => 16900.00,
                'is_featured' => false,
                'status' => 'active',
                'color' => 'Blush Rose',
                'color_hex' => '#FFD1DC',
                'images' => [
                    'https://images.unsplash.com/photo-1609357605129-26f69add5d6e?auto=format&fit=crop&w=1000&q=80',
                    'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=1000&q=80',
                ],
                'sizes' => ['S', 'M', 'L', 'Custom'],
            ],
        ];

        $firstProduct = null;
        $firstVariant = null;

        foreach ($productsData as $prodIndex => $item) {
            $product = Product::firstOrCreate(
                ['slug' => $item['slug']],
                [
                    'category_id' => $item['category_id'],
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'fabric_type' => $item['fabric_type'],
                    'piece_type' => $item['piece_type'],
                    'base_price' => $item['base_price'],
                    'sale_price' => $item['sale_price'],
                    'is_featured' => $item['is_featured'],
                    'status' => $item['status'],
                ]
            );

            if ($prodIndex === 0) {
                $firstProduct = $product;
            }

            // Images
            foreach ($item['images'] as $imgIndex => $url) {
                ProductImage::firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'image_url' => $url,
                    ],
                    [
                        'alt_text' => $product->name . ' - Angle ' . ($imgIndex + 1),
                        'is_primary' => $imgIndex === 0,
                        'sort_order' => $imgIndex,
                    ]
                );
            }

            // Variants
            foreach ($item['sizes'] as $vIndex => $size) {
                $sku = strtoupper(substr($item['fabric_type'], 0, 2)) . '-' . $product->id . '-' . $size;
                $variant = ProductVariant::firstOrCreate(
                    ['sku' => $sku],
                    [
                        'product_id' => $product->id,
                        'size' => $size,
                        'color' => $item['color'],
                        'color_hex' => $item['color_hex'],
                        'price_override' => $size === 'Custom' ? ($product->sale_price ?? $product->base_price) + 300.00 : null,
                        'stock_quantity' => $size === 'Custom' ? 999 : (10 + ($vIndex * 5)),
                        'is_active' => true,
                    ]
                );

                if ($prodIndex === 0 && $vIndex === 1) {
                    $firstVariant = $variant;
                }
            }
        }

        // 5. Coupons
        Coupon::firstOrCreate(
            ['code' => 'WELCOME10'],
            [
                'discount_type' => 'percentage',
                'discount_value' => 10.00,
                'min_order_amount' => 3000.00,
                'max_uses' => 200,
                'used_count' => 12,
                'expires_at' => now()->addMonths(6),
                'is_active' => true,
            ]
        );

        Coupon::firstOrCreate(
            ['code' => 'LUXURY500'],
            [
                'discount_type' => 'fixed',
                'discount_value' => 500.00,
                'min_order_amount' => 5000.00,
                'max_uses' => 100,
                'used_count' => 5,
                'expires_at' => now()->addMonths(3),
                'is_active' => true,
            ]
        );

        // 6. Customer Measurements
        $measurement = CustomerMeasurement::firstOrCreate(
            [
                'user_id' => $customer->id,
                'profile_name' => 'Ayesha Standard Fit',
            ],
            [
                'unit' => 'inches',
                'shirt_length' => 38.5,
                'shoulder' => 14.5,
                'chest' => 38.0,
                'waist' => 34.0,
                'hips' => 41.0,
                'sleeve_length' => 22.0,
                'armhole' => 8.5,
                'daman' => 23.0,
                'neck_front' => 6.5,
                'neck_back' => 3.5,
                'trouser_length' => 37.0,
                'trouser_waist' => 30.0,
                'pancha' => 7.0,
                'extra_notes' => 'Please keep armholes slightly relaxed for comfort.',
            ]
        );

        // 7. Sample Orders
        if ($firstProduct && $firstVariant) {
            $order1 = Order::firstOrCreate(
                ['order_number' => 'KF-2026-0001'],
                [
                    'user_id' => $customer->id,
                    'customer_name' => 'Ayesha Khan',
                    'customer_phone' => '+923211234567',
                    'customer_email' => 'ayesha@kfabrics.com',
                    'shipping_address' => 'House 14-B, Street 5, Gulberg III',
                    'city' => 'Lahore',
                    'subtotal' => 7500.00,
                    'shipping_fee' => 0.00,
                    'discount_amount' => 500.00,
                    'total_amount' => 7000.00,
                    'payment_method' => 'cod',
                    'payment_status' => 'pending',
                    'order_status' => 'confirmed',
                    'courier_name' => 'TCS Express',
                    'tracking_number' => 'TCS-99210291',
                    'notes' => 'Deliver before 5 PM on weekdays.',
                ]
            );

            $orderItem1 = OrderItem::firstOrCreate(
                [
                    'order_id' => $order1->id,
                    'product_id' => $firstProduct->id,
                ],
                [
                    'variant_id' => $firstVariant->id,
                    'product_name' => $firstProduct->name,
                    'size' => $firstVariant->size,
                    'color' => $firstVariant->color,
                    'quantity' => 1,
                    'unit_price' => 7200.00,
                    'total_price' => 7200.00,
                ]
            );

            StitchingRequest::firstOrCreate(
                ['order_id' => $order1->id, 'request_type' => 'custom_fitting'],
                [
                    'order_item_id' => $orderItem1->id,
                    'stitching_service_id' => $threePieceFinishing->id,
                    'measurement_id' => $measurement->id,
                    'neck_style' => 'V-Neck with delicate lace insert',
                    'sleeve_style' => 'Straight cuffs with organza edge',
                    'trouser_style' => 'Straight ankle trousers',
                    'special_instructions' => 'Follow measurement profile exactly.',
                    'stitching_fee' => 300.00,
                    'status' => 'stitching',
                ]
            );

            // Standalone stitching order
            $order2 = Order::firstOrCreate(
                ['order_number' => 'KF-2026-0002'],
                [
                    'user_id' => $customer->id,
                    'customer_name' => 'Ayesha Khan',
                    'customer_phone' => '+923211234567',
                    'customer_email' => 'ayesha@kfabrics.com',
                    'shipping_address' => 'House 14-B, Street 5, Gulberg III',
                    'city' => 'Lahore',
                    'subtotal' => 2200.00,
                    'shipping_fee' => 250.00,
                    'discount_amount' => 0.00,
                    'total_amount' => 2450.00,
                    'payment_method' => 'bank_transfer',
                    'payment_status' => 'paid',
                    'order_status' => 'stitching',
                    'courier_name' => 'Trax Logistics',
                    'tracking_number' => 'TRX-8819201',
                    'notes' => 'Customer sent unstitched Khaadi lawn fabric via rider pickup.',
                ]
            );

            StitchingRequest::firstOrCreate(
                ['order_id' => $order2->id, 'request_type' => 'standalone_stitching'],
                [
                    'order_item_id' => null,
                    'stitching_service_id' => $threePieceFinishing->id,
                    'measurement_id' => $measurement->id,
                    'fabric_delivery_method' => 'rider_pickup',
                    'fabric_received_at' => now()->subDays(2),
                    'design_reference_image' => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=1000&q=80',
                    'neck_style' => 'Round with button loop slit',
                    'sleeve_style' => 'Bell sleeves',
                    'trouser_style' => 'Cigarette pants',
                    'special_instructions' => 'Fabric received and inspected. In cutting room.',
                    'stitching_fee' => 2200.00,
                    'status' => 'cutting',
                ]
            );
        }

        $this->call(ProductReviewSeeder::class);
    }
}
