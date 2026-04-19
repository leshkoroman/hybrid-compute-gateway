<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            MaterialSeeder::class,
            QuantumDotTypeSeeder::class,
            CalculationSeeder::class,
        ]);
    }
}