<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFitGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_measurements_do_not_leak_to_other_products_and_unverified_data_stays_blank(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$first, $other] = Product::query()->where('status', 'active')->orderBy('id')->take(2)->get()->all();

        $first->update([
            'size_chart_type' => 'garment',
            'size_chart' => [['size' => 'XXL', 'bust' => 46, 'length' => 42.5]],
            'fit_notes' => 'Model is 170 cm and wears M.',
            'care_instructions' => 'Follow the label.',
        ]);

        $this->getJson("/api/v1/products/{$first->slug}")
            ->assertOk()
            ->assertJsonPath('data.size_chart_type', 'garment')
            ->assertJsonPath('data.size_chart.0.size', 'XXL')
            ->assertJsonPath('data.size_chart.0.bust', 46)
            ->assertJsonPath('data.size_chart.0.length', 42.5)
            ->assertJsonPath('data.fit_notes', 'Model is 170 cm and wears M.')
            ->assertJsonPath('data.care_instructions', 'Follow the label.');

        $this->getJson("/api/v1/products/{$other->slug}")
            ->assertOk()
            ->assertJsonPath('data.size_chart_type', null)
            ->assertJsonPath('data.size_chart', [])
            ->assertJsonPath('data.fit_notes', null);
    }
}
