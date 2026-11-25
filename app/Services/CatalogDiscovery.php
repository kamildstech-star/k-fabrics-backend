<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

class CatalogDiscovery
{
    private const SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Custom'];

    public function publicProducts(): Builder
    {
        return Product::query()
            ->where('status', 'active')
            ->whereHas('category', function (Builder $query): void {
                $query->where('is_active', true)->where(function (Builder $query): void {
                    $query->whereNull('parent_id')
                        ->orWhereHas('parent', fn (Builder $parent) => $parent->where('is_active', true));
                });
            });
    }

    public function products(array $filters): Builder
    {
        $query = $this->discoveryScope($filters);

        foreach (['fabric_type', 'piece_type'] as $key) {
            if (! empty($filters[$key])) {
                $labels = (clone $query)->distinct()->pluck($key);
                $matches = $this->matchingLabels($labels, $filters[$key]);
                // The old menu used a hard-coded Silk value. Preserve old links when
                // the actual catalog only contains more specific silk labels.
                if ($key === 'fabric_type' && $matches === [] && self::facetId($filters[$key]) === 'silk') {
                    $matches = $labels->filter(fn (string $label) => in_array('silk', explode('-', self::facetId($label)), true))->all();
                }
                $query->whereIn($key, $matches);
            }
        }

        if (! empty($filters['size']) || ! empty($filters['color'])) {
            $sizes = isset($filters['size']) ? $this->matchingLabels(self::SIZES, $filters['size']) : null;
            $colors = ! empty($filters['color'])
                ? $this->matchingLabels(ProductVariant::query()
                    ->whereIn('product_id', (clone $query)->select('products.id'))
                    ->whereNotNull('color')->distinct()->pluck('color'), $filters['color'])
                : null;

            // Keep both conditions in the same existence check. An M in red and an S in
            // blue can never satisfy a request for an in-stock M in blue.
            $query->whereHas('variants', function (Builder $variants) use ($sizes, $colors): void {
                $this->sellable($variants);
                if ($sizes !== null) {
                    $variants->whereIn('size', $sizes);
                }
                if ($colors !== null) {
                    $variants->whereIn('color', $colors);
                }
            });
        }

        if (isset($filters['min_price'])) {
            $query->whereRaw('COALESCE(sale_price, base_price) >= CAST(? AS DECIMAL(12,2))', [$filters['min_price']]);
        }
        if (isset($filters['max_price'])) {
            $query->whereRaw('COALESCE(sale_price, base_price) <= CAST(? AS DECIMAL(12,2))', [$filters['max_price']]);
        }
        if (isset($filters['is_featured'])) {
            $featured = filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($featured !== null) {
                $query->where('is_featured', $featured);
            }
        }

        return $query;
    }

    public function discoveryScope(array $filters): Builder
    {
        $query = $this->publicProducts();
        if (! empty($filters['category'])) {
            $slug = self::facetId($filters['category']);
            $query->whereHas('category', function (Builder $categories) use ($slug): void {
                $categories->where('slug', $slug)
                    ->orWhereHas('parent', fn (Builder $parent) => $parent->where('slug', $slug));
            });
        }
        if (isset($filters['search'])) {
            $this->search($query, $filters['search']);
        }

        return $query;
    }

    private function search(Builder $query, string $search): void
    {
        $tokens = self::tokens($search);
        if ($tokens === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        foreach ($tokens as $token) {
            $pattern = '%'.self::escapeLike($token).'%';
            $query->where(function (Builder $matches) use ($pattern, $token): void {
                $matches->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern]);
                foreach (['slug', 'description', 'fabric_type', 'piece_type'] as $column) {
                    $matches->orWhereRaw("LOWER($column) LIKE ? ESCAPE '!'", [$pattern]);
                }
                $matches->orWhereHas('category', function (Builder $categories) use ($pattern): void {
                    $this->categoryText($categories, $pattern)
                        ->orWhereHas('parent', fn (Builder $parent) => $this->categoryText($parent, $pattern));
                });
                $matches->orWhereHas('variants', function (Builder $variants) use ($pattern, $token): void {
                    $variants->where('is_active', true)->where(function (Builder $variants) use ($pattern, $token): void {
                        $variants->whereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [$pattern])
                            ->orWhereRaw("LOWER(color) LIKE ? ESCAPE '!'", [$pattern]);
                        if (mb_strlen($token) >= 2) {
                            $variants->orWhereRaw('LOWER(size) = ?', [$token]);
                        }
                    });
                });
            });
        }
    }

    private function categoryText(Builder $query, string $pattern): Builder
    {
        return $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
            ->orWhereRaw("LOWER(slug) LIKE ? ESCAPE '!'", [$pattern]);
    }

    public function order(Builder $query, ?string $sort, ?string $search): Builder
    {
        $sort ??= $search !== null ? 'relevance' : 'newest';
        if ($sort === 'price_asc' || $sort === 'price_desc') {
            $direction = $sort === 'price_asc' ? 'ASC' : 'DESC';
            $query->orderByRaw("COALESCE(sale_price, base_price) $direction");
        } elseif ($sort === 'relevance' && $search !== null && ($tokens = self::tokens($search)) !== []) {
            $phrase = implode(' ', $tokens);
            $escaped = self::escapeLike($phrase);
            $query->orderByRaw(
                "CASE WHEN LOWER(name) = ? THEN 0 WHEN LOWER(name) LIKE ? ESCAPE '!' THEN 1 WHEN LOWER(name) LIKE ? ESCAPE '!' THEN 2 ELSE 3 END",
                [$phrase, $escaped.'%', '%'.$escaped.'%'],
            );
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function facets(array $filters): array
    {
        $productSelection = $this->discoveryScope($filters)->whereHas('variants', fn (Builder $query) => $this->sellable($query));
        $inventorySelection = $this->sellable(ProductVariant::query())
            ->whereIn('product_id', (clone $productSelection)->select('products.id'));

        $sizes = (clone $inventorySelection)->distinct()->pluck('size');
        $orderedSizes = array_values(array_intersect(self::SIZES, $sizes->all()));

        return [
            'sizes' => $this->facetOptions($orderedSizes),
            'fabrics' => $this->facetOptions((clone $productSelection)->distinct()->orderBy('fabric_type')->pluck('fabric_type')),
            'pieces' => $this->facetOptions((clone $productSelection)->distinct()->orderBy('piece_type')->pluck('piece_type')),
            'colors' => $this->facetOptions((clone $inventorySelection)->whereNotNull('color')->distinct()->orderBy('color')->pluck('color')),
        ];
    }

    private function sellable(Builder $variants): Builder
    {
        $customEnabled = app(CheckoutPricing::class)->bankInstructions() !== null;

        return $variants->where('is_active', true)->where(function (Builder $query) use ($customEnabled): void {
            $query->where(fn (Builder $standard) => $standard->where('size', '<>', 'Custom')->where('stock_quantity', '>', 0));
            // A made-to-order size needs a working advance flow, not inventory.
            if ($customEnabled) {
                $query->orWhere('size', 'Custom');
            }
        });
    }

    private function matchingLabels(iterable $labels, string $input): array
    {
        $id = self::facetId($input);
        if ($id === '') {
            return [];
        }

        $matches = [];
        foreach ($labels as $label) {
            if (self::facetId($label) === $id) {
                $matches[] = $label;
            }
        }

        return array_values(array_unique($matches));
    }

    private function facetOptions(iterable $labels): array
    {
        $options = [];
        foreach ($labels as $label) {
            $id = self::facetId($label);
            if ($id !== '' && ! isset($options[$id])) {
                $options[$id] = ['id' => $id, 'label' => trim($label)];
            }
        }

        return array_values($options);
    }

    private static function facetId(string $label): string
    {
        return implode('-', preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($label)), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    private static function tokens(string $input): array
    {
        $text = trim($input);
        if (mb_strlen($text) < 2) {
            return [];
        }

        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (mb_strlen(implode('', $parts)) < 2) {
            return [];
        }

        return array_slice(array_values(array_unique($parts)), 0, 8);
    }

    private static function escapeLike(string $label): string
    {
        return strtr($label, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }
}
