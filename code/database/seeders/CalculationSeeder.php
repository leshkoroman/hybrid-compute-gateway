<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class CalculationSeeder extends Seeder
{
    public function run()
    {
        // 1. Створюємо тестового користувача
        $user = User::firstOrCreate(
            ['email' => 'admin@science.com'],
            [
                'name' => 'Science Admin',
                'password' => Hash::make('12345678') // Простий пароль для тестів
            ]
        );

        // 2. Створюємо фейкові обчислення
        DB::table('calculations')->insert([
            [
                'user_id' => $user->id,
                'quantum_dot_type_id' => 1, // Сферична
                'core_material_id' => 1, // GaAs
                'matrix_material_id' => 2, // AlAs
                'geometry_params' => json_encode(['radius' => 8.5]),
                'status' => 'completed',
                'results_path' => 'results/calc_1',
                'error_log' => null,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'user_id' => $user->id,
                'quantum_dot_type_id' => 2, // Кубічна
                'core_material_id' => 2, // InAs
                'matrix_material_id' => 1, // GaAs
                'geometry_params' => json_encode(['length' => 12.0]),
                'status' => 'failed',
                'results_path' => null,
                'error_log' => 'MPI_ERR_RANK: Invalid rank. Process terminated unexpectedly.',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'user_id' => $user->id,
                'quantum_dot_type_id' => 3, // Циліндрична
                'core_material_id' => 1,
                'matrix_material_id' => 2,
                'geometry_params' => json_encode(['radius' => 10.0, 'height' => 15.0]),
                'status' => 'processing',
                'results_path' => null,
                'error_log' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}