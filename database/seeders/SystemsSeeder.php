<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\System;
use App\Models\Product;

class SystemsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Create a system
        $system = System::create(['name' => 'Basic Solar System']);

        // Retrieve the products
        $solarPanel = Product::where('name', 'Solar panel')->first();
        $inverter = Product::where('name', 'Inverter')->first();
        $optimizer = Product::where('name', 'Optimizer')->first();

        // Attach products to the system with quantities
        $system->products()->attach([
            $solarPanel->id => ['quantity' => 12],
            $inverter->id   => ['quantity' => 1],
            $optimizer->id  => ['quantity' => 12],
        ]);
    }
}