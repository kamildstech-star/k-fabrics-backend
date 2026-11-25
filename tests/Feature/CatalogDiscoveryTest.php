<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_and_predictive_share_category_fabric_and_item_matching(): void
    {
        $parent = $this->category('Festive Edit');
        $child = $this->category('Eveningwear', $parent);
        $primary = $this->product('Ivory Drape', $child, 'Raw Silk');
        $this->variant($primary, 'M', 'Ivory', 3, sku: 'ATELIER-42');
        $secondary = $this->product('Silver Drape', $child, 'Raw Silk');
        $this->variant($secondary, 'M', 'Silver', 0, sku: 'ATELIER-43');
        $hiddenParent = $this->category('Archive Festive', active: false);
        $hiddenChild = $this->category('Evening Archive', $hiddenParent);
        $this->product('Ivory Hidden', $hiddenChild, 'Raw Silk');
        $this->product('Ivory Draft', $child, 'Raw Silk')->update(['status' => 'draft']);

        foreach ([
            '  FESTIVE    raw-silk  ' => [$secondary->slug, $primary->slug],
            'eveningwear Ivory' => [$primary->slug],
            'Atelier 42' => [$primary->slug],
            'IVORY' => [$primary->slug],
            'nothing matches' => [],
            '%__' => [],
            'i' => [],
        ] as $term => $expected) {
            $suggestions = $this->getJson('/api/v1/search/suggest?'.http_build_query(['q' => $term]))->assertOk();
            $listing = $this->getJson('/api/v1/products?'.http_build_query(['search' => $term]))->assertOk();
            $this->assertSame($expected, array_column($listing->json('data'), 'slug'), $term);
            $this->assertSame($expected, array_column($suggestions->json('data'), 'slug'), $term);
        }
        $this->getJson('/api/v1/products/ivory-hidden')->assertNotFound();
    }

    public function test_filters_and_facets_use_canonical_in_stock_values_and_same_variant(): void
    {
        $parent = $this->category('Pret');
        $child = $this->category('Daily', $parent);
        $inStock = $this->product('Available', $child, 'Raw Silk');
        $this->variant($inStock, 'S', 'Deep Blue', 3);
        $this->variant($inStock, 'M', 'Crimson', 2);
        $this->variant($inStock, 'M', 'Deep Blue', 0);
        $this->variant($inStock, 'XXL', 'Limited Pink', 6, active: false);
        $this->variant($inStock, 'Custom', 'Ivory', 0);
        $soldOut = $this->product('Sold Out', $child, 'Linen');
        $this->variant($soldOut, 'XL', 'Unavailable Lilac', 0);
        $other = $this->product('Other', $this->category('Formal'), 'Chiffon');
        $this->variant($other, 'L', 'Black', 3);
        $hidden = $this->product('Hidden', $this->category('Inactive', active: false), 'Velvet');
        $this->variant($hidden, 'XXL', 'Emerald', 5);

        $facets = $this->getJson('/api/v1/products/facets?category=pret')->assertOk();
        $this->assertSame([
            ['id' => 's', 'label' => 'S'],
            ['id' => 'm', 'label' => 'M'],
            ['id' => 'custom', 'label' => 'Custom'],
        ], $facets->json('data.sizes'));
        $this->assertSame([['id' => 'raw-silk', 'label' => 'Raw Silk']], $facets->json('data.fabrics'));
        $this->assertSame(['crimson', 'deep-blue', 'ivory'], array_column($facets->json('data.colors'), 'id'));
        $this->assertSame([['id' => '2-piece', 'label' => '2-piece']], $facets->json('data.pieces'));
        $this->getJson('/api/v1/products/facets?category=pret&search=Sold+Out')
            ->assertOk()->assertJsonCount(0, 'data.sizes')->assertJsonCount(0, 'data.fabrics');

        $this->getJson('/api/v1/products?category=pret')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/products?size=m&color=deep-blue')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/products?size=s&color=deep-blue&fabric_type=raw-silk&piece_type=2-piece')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', $inStock->slug);
        $this->getJson('/api/v1/products?size=S&fabric_type=Raw%20Silk')
            ->assertOk()->assertJsonPath('data.0.slug', $inStock->slug);
        $this->getJson('/api/v1/products?size=custom&color=ivory')->assertOk()->assertJsonPath('data.0.slug', $inStock->slug);
        $this->getJson('/api/v1/products?size=xxl')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/products?fabric_type=silk')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $inStock->slug);
    }

    public function test_sort_and_price_filters_are_deterministic_and_query_parameters_are_validated(): void
    {
        $category = $this->category('Evening');
        $exact = $this->product('Drape', $category, 'Lawn', 1500);
        $middle = $this->product('Drape Midnight', $category, 'Lawn', 2000);
        $contained = $this->product('Ivory Drape', $category, 'Lawn', 1000);
        $search = '/api/v1/products?search=drape';

        $this->getJson($search.'&sort=relevance')->assertOk()->assertJsonPath('data.0.slug', $exact->slug);
        $this->getJson($search.'&sort=price_asc')->assertOk()->assertJsonPath('data.0.slug', $contained->slug);
        $this->getJson($search.'&sort=newest')->assertOk()->assertJsonPath('data.0.slug', $contained->slug);
        $this->getJson($search.'&max_price=1500')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($search.'&min_price=2000')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', $middle->slug);
        $this->getJson($search.'&min_price=2000&max_price=1000')->assertUnprocessable()->assertJsonValidationErrors('max_price');
        $this->getJson('/api/v1/search/suggest?q%5B%5D=drape')->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson('/api/v1/products?color%5B%5D=deep-blue')->assertUnprocessable()->assertJsonValidationErrors('color');
    }

    private function category(string $name, ?Category $parent = null, bool $active = true): Category
    {
        return Category::create(['name' => $name, 'slug' => Str::slug($name), 'parent_id' => $parent?->id, 'is_active' => $active]);
    }

    private function product(string $name, Category $category, string $fabric, int $price = 1000): Product
    {
        return Product::create([
            'category_id' => $category->id, 'name' => $name, 'slug' => Str::slug($name),
            'fabric_type' => $fabric, 'piece_type' => '2-piece', 'base_price' => $price, 'status' => 'active',
        ]);
    }

    private function variant(Product $product, string $size, string $color, int $stock, ?string $sku = null, bool $active = true): ProductVariant
    {
        return $product->variants()->create([
            'size' => $size, 'color' => $color, 'stock_quantity' => $stock,
            'sku' => $sku ?? Str::uuid()->toString(), 'is_active' => $active,
        ]);
    }
}
