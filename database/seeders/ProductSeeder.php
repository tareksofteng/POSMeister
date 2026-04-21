<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductCategory;
use App\Modules\Product\Models\Brand;
use App\Modules\Product\Models\Unit;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categoryIds = ProductCategory::pluck('id')->toArray();
        $brandIds    = Brand::pluck('id')->toArray();
        $unitIds     = Unit::pluck('id')->toArray();

        Product::factory()
            ->count(10000)
            ->make()
            ->each(function ($product) use ($categoryIds, $brandIds, $unitIds) {
                $product->category_id = fake()->randomElement($categoryIds);
                $product->brand_id    = fake()->randomElement($brandIds);
                $product->unit_id     = fake()->randomElement($unitIds);
                $product->save();
            });
    }
}
