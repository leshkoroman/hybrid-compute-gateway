<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Calculation extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Автоматичне перетворення JSON у масив
    protected $casts = [
        'geometry_params' => 'array',
    ];

    // Зв'язок: Кому належить розрахунок
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Зв'язок: Тип геометрії
    public function quantumDotType()
    {
        return $this->belongsTo(QuantumDotType::class);
    }

    // Зв'язок: Матеріал ядра
    public function coreMaterial()
    {
        return $this->belongsTo(Material::class, 'core_material_id');
    }

    // Зв'язок: Матеріал матриці (оболонки)
    public function matrixMaterial()
    {
        return $this->belongsTo(Material::class, 'matrix_material_id');
    }
}