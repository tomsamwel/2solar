<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;


class ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        Product::create([
            'name' => 'Solar panel',
            'initial_stock' => 1000,
            'stock' => 1000,
            'low_stock_notified' => false,
        ]);
        
        Product::create([
            'name' => 'Inverter',
            'initial_stock' => 100,
            'stock' => 100,
            'low_stock_notified' => false,
        ]);
        
        Product::create([
            'name' => 'Optimizer',
            'initial_stock' => 500,
            'stock' => 500,
            'low_stock_notified' => false,
        ]);
    }
}
