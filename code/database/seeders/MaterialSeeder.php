<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialSeeder extends Seeder
{
    public function run()
    {
        DB::table('materials')->insert([
            ['name' => 'GaAs', 'v_pot' => 0.0, 'gamma1' => 7.08, 'gamma2' => 2.56, 'gamma3' => 2.56],
            ['name' => 'AlAs', 'v_pot' => 562.0, 'gamma1' => 3.76, 'gamma2' => 1.18, 'gamma3' => 1.18],
            ['name' => 'InAs', 'v_pot' => -150.0, 'gamma1' => 20.4, 'gamma2' => 8.3, 'gamma3' => 9.1],
        ]);
    }
}