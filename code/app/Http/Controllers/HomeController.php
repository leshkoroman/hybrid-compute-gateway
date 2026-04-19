<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\QuantumDotType;
use App\Models\Calculation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Jobs\RunQuantumCalculation;
use Exception;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        // Кешуємо типи КТ та матеріали для оптимізації
        $dotTypes = Cache::rememberForever('quantum_dot_types', fn() => QuantumDotType::all());
        $materials = Cache::rememberForever('materials', fn() => Material::all());

        $calculations = Calculation::with(['quantumDotType', 'coreMaterial', 'matrixMaterial'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(5);

        return view('home', compact('dotTypes', 'materials', 'calculations'));
    }

    /**
     * Обробляє створення новогорозрахунку
     */
    public function storeCalculation(Request $request)
    {
        // 1. Валідація ПОЗА блоком try-catch, щоб Laravel міг магічно 
        // повернути юзера на форму з підсвічуванням конкретних полів
        $validated = $this->validateCalculation($request);

        // 2. Системні дії обгортаємо в try-catch
        try {
            Log::info('Створення розрахунку', [
                'user_id' => Auth::id(),
                'type_id' => $validated['quantum_dot_type_id'],
            ]);

            // Створюємо запис у БД
            $calculation = Calculation::create([
                'user_id' => Auth::id(),
                'quantum_dot_type_id' => $validated['quantum_dot_type_id'],
                'core_material_id' => $validated['core_material_id'],
                'matrix_material_id' => $validated['matrix_material_id'],
                'geometry_params' => $validated['geometry_params'],
                'status' => 'pending',
            ]);

            // Відправляємо завдання у чергу
            RunQuantumCalculation::dispatch($calculation);

            Log::info('Розрахунок успішно додано в чергу', ['calculation_id' => $calculation->id]);

            return back()->with('status', 'Розрахунок успішно додано в чергу!');
            
        } catch (Exception $e) {
            Log::error('Помилка при створенні розрахунку: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withErrors(['error' => 'Помилка при обробці розрахунку. Спробуйте ще раз.'])
                ->withInput();
        }
    }

    /**
     * Валідує дані розрахунку в залежності від типу квантової точки
     */
    private function validateCalculation(Request $request): array
    {
        // Базова валідація
        $request->validate([
            'quantum_dot_type_id' => 'required|exists:quantum_dot_types,id',
            'core_material_id' => 'required|exists:materials,id',
            'matrix_material_id' => 'required|exists:materials,id',
        ]);

        $type = QuantumDotType::findOrFail($request->quantum_dot_type_id);
        $geometry_params = [];

        // Специфічна валідація залежно від форми КТ
        switch ($type->slug) {
            case 'spherical':
                $request->validate(['radius' => 'required|numeric|min:0.1']);
                $geometry_params['radius'] = (float) $request->radius;
                break;

            case 'cubic':
                $request->validate(['length' => 'required|numeric|min:0.1']);
                $geometry_params['length'] = (float) $request->length;
                break;

            case 'cylindrical':
            case 'conical':
                $request->validate([
                    'radius' => 'required|numeric|min:0.1',
                    'height' => 'required|numeric|min:0.1',
                ]);
                $geometry_params['radius'] = (float) $request->radius;
                $geometry_params['height'] = (float) $request->height;
                break;
        }

        return [
            'quantum_dot_type_id' => $type->id,
            'core_material_id' => $request->core_material_id,
            'matrix_material_id' => $request->matrix_material_id,
            'geometry_params' => $geometry_params,
        ];
    }
}