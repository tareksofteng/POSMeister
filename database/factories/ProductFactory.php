<?php

namespace Database\Factories;

use App\Modules\Product\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'sku' => strtoupper(Str::random(8)),
            'name' => ucfirst($name),
            'description' => $this->faker->sentence(10),
            'image' => 'https://picsum.photos/seed/' . Str::random(10) . '/300/300',

            // ❌ REMOVE THESE (important — already handled in Seeder)
            // 'category_id' => ProductCategory::inRandomOrder()->value('id'),
            // 'brand_id'    => Brand::inRandomOrder()->value('id'),
            // 'unit_id'     => Unit::inRandomOrder()->value('id'),

            'barcode' => $this->faker->ean13(),

            'cost_price'        => $this->faker->randomFloat(2, 10, 500),
            'selling_price'     => $this->faker->randomFloat(2, 100, 1000),
            'wholesale_price'   => $this->faker->randomFloat(2, 80, 900),
            'min_selling_price' => $this->faker->randomFloat(2, 50, 800),

            'tax_rate' => $this->faker->randomElement([0, 5, 10, 15]),
            'reorder_level' => rand(5, 50),

            'is_service' => false,
            'is_active'  => true,

            'created_by' => 1,
        ];
    }
}
