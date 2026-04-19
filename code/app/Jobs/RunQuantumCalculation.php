<?php
// app/Jobs/RunQuantumCalculation.php
namespace App\Jobs;

use App\Models\Calculation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;

class RunQuantumCalculation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $calculation;

    public $timeout = 7200; 

    public function __construct(Calculation $calculation)
    {
        $this->calculation = $calculation;
    }

    public function handle()
    {
        $this->calculation->update(['status' => 'processing']);

        $scriptName = $this->calculation->quantumDotType->script_path;
        $scriptPath = base_path('scripts/' . $scriptName);
        $calculationId = $this->calculation->id;
        $outputDir = storage_path('app/public/results/calc_' . $calculationId);
        
        // Отримуємо матеріали (Eloquent автоматично підтягне їх з бази)
        $core = $this->calculation->coreMaterial;
        $matrix = $this->calculation->matrixMaterial;

        // Формуємо базовий масив команди
        $command = [
            'python3', 
            $scriptPath, 
            '--output_dir=' . $outputDir,
            
            // Фізичні параметри ядра
            '--core_v_pot=' . $core->v_pot,
            '--core_g1=' . $core->gamma1,
            '--core_g2=' . $core->gamma2,
            '--core_g3=' . $core->gamma3,

            // Фізичні параметри матриці (оболонки)
            '--matrix_v_pot=' . $matrix->v_pot,
            '--matrix_g1=' . $matrix->gamma1,
            '--matrix_g2=' . $matrix->gamma2,
            '--matrix_g3=' . $matrix->gamma3,
        ];

        // Динамічно додаємо геометричні параметри
        // Якщо це сфера, додасться: --radius=8.5
        // Якщо циліндр: --radius=10.0 --height=15.0
        foreach ($this->calculation->geometry_params as $key => $value) {
            $command[] = "--{$key}={$value}";
        }

        $process = new Process($command);
        $process->setTimeout(null);

        try {
            // Запускаємо скрипт
            $process->mustRun();

            $this->calculation->update([
                'status' => 'completed',
                'results_path' => 'results/calc_' . $calculationId
            ]);

        } catch (ProcessFailedException $exception) {
            Log::error('Calculation Failed: ' . $exception->getMessage());
            
            $this->calculation->update([
                'status' => 'failed',
                'error_log' => $exception->getMessage()
            ]);
        }
    }
}