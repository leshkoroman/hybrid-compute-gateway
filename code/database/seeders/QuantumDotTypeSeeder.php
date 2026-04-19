<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuantumDotTypeSeeder extends Seeder
{
    public function run()
    {
        DB::table('quantum_dot_types')->insert([
            ['name' => 'Сферична', 'slug' => 'spherical', 'script_path' => 'calc_spherical_mpi.py'],
            ['name' => 'Кубічна', 'slug' => 'cubic', 'script_path' => 'calc_cubic_mpi.py'],
            ['name' => 'Циліндрична', 'slug' => 'cylindrical', 'script_path' => 'calc_cylindrical_mpi.py'],
            ['name' => 'Конічна', 'slug' => 'conical', 'script_path' => 'calc_conical_mpi.py'],
        ]);
    }
}