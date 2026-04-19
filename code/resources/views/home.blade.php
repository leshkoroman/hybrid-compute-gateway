@extends('layouts.app')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid px-4">
    <div class="row">
        
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-play-circle me-2"></i> Нове обчислення
                </div>
                <div class="card-body">
                    <form action="{{ route('calculate.store') }}" method="POST">
                        @csrf
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small text-uppercase">Тип квантової точки</label>
                            <select class="form-select" name="quantum_dot_type_id" id="quantum_dot_type_select" required>
                                <option value="" disabled selected>Оберіть геометрію...</option>
                                @foreach($dotTypes as $type)
                                    <option value="{{ $type->id }}" data-slug="{{ $type->slug }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small text-uppercase">Матеріал ядра (Core)</label>
                            <select class="form-select" name="core_material_id" required>
                                @foreach($materials as $material)
                                    <option value="{{ $material->id }}">{{ $material->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small text-uppercase">Матеріал матриці (Shell)</label>
                            <select class="form-select" name="matrix_material_id" required>
                                @foreach($materials as $material)
                                    <option value="{{ $material->id }}" {{ $material->name == 'AlAs' ? 'selected' : '' }}>{{ $material->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="p-3 bg-light border rounded mb-3" id="geometry_params_container" style="display: none;">
                            
                            <div class="mb-2" id="group_radius" style="display: none;">
                                <label class="form-label text-muted small text-uppercase">Радіус основи R (нм)</label>
                                <input type="number" step="0.1" min="0.1" class="form-control" name="radius" id="input_radius" placeholder="Наприклад: 8.5">
                            </div>

                            <div class="mb-2" id="group_height" style="display: none;">
                                <label class="form-label text-muted small text-uppercase">Висота H (нм)</label>
                                <input type="number" step="0.1" min="0.1" class="form-control" name="height" id="input_height" placeholder="Наприклад: 15.0">
                            </div>

                            <div class="mb-2" id="group_length" style="display: none;">
                                <label class="form-label text-muted small text-uppercase">Довжина ребра L (нм)</label>
                                <input type="number" step="0.1" min="0.1" class="form-control" name="length" id="input_length" placeholder="Наприклад: 10.0">
                            </div>

                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-cpu me-1"></i> Запустити розрахунок
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-table me-2"></i> Мої обчислення</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="location.reload();">
                        <i class="bi bi-arrow-clockwise"></i> Оновити
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Геометрія</th>
                                    <th>Матеріали (Core / Matrix)</th>
                                    <th>Статус</th>
                                    <th>Дата запуску</th>
                                    <th class="text-end">Дії</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($calculations as $calc)
                                    <tr>
                                        <td>#{{ $calc->id }}</td>
                                        <td>
                                            {{ $calc->quantumDotType->name }} 
                                            <span class="text-muted small">
                                                (@foreach($calc->geometry_params as $key => $value) {{ ucfirst($key) }}={{ $value }} @endforeach)
                                            </span>
                                        </td>
                                        <td>{{ $calc->coreMaterial->name }} / {{ $calc->matrixMaterial->name }}</td>
                                        <td>
                                            @if($calc->status == 'pending')
                                                <span class="badge bg-secondary"><i class="bi bi-clock"></i> В черзі</span>
                                            @elseif($calc->status == 'processing')
                                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split"></i> В процесі</span>
                                            @elseif($calc->status == 'completed')
                                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Готово</span>
                                            @else
                                                <span class="badge bg-danger"><i class="bi bi-x-circle"></i> Помилка</span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">{{ $calc->created_at->format('d.m.Y H:i') }}</td>
                                        <td class="text-end">
                                            @if($calc->status == 'completed')
                                                <button type="button" class="btn btn-sm btn-primary btn-show-chart" 
                                                        data-id="{{ $calc->id }}" 
                                                        data-type="{{ $calc->quantumDotType->slug }}">
                                                    <i class="bi bi-graph-up"></i> Графіки
                                                </button>
                                            @elseif($calc->status == 'failed')
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#errorLogModal" 
                                                        data-log="{{ $calc->error_log }}">
                                                    <i class="bi bi-bug"></i> Лог
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-light" disabled>Очікування...</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">У вас ще немає запущених розрахунків.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-center mt-3">
                        {{ $calculations->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="errorLogModal" tabindex="-1" aria-labelledby="errorLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="errorLogModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Деталі помилки розрахунку
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre id="modal-log-content" class="bg-light p-3 border rounded text-danger" style="white-space: pre-wrap; font-family: monospace; font-size: 0.875rem; max-height: 400px; overflow-y: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрити</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="chartModal" tabindex="-1" aria-labelledby="chartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="chartModalLabel">
                    <i class="bi bi-graph-up me-2"></i> Енергетичний спектр
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="chart-loading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Завантаження даних (AJAX)...</p>
                </div>
                <canvas id="energyChart" style="display: none; max-height: 500px;"></canvas>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрити</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --------------------------------------------------------
    // 1. Логіка відображення полів форми
    // --------------------------------------------------------
    const select = document.getElementById('quantum_dot_type_select');
    const container = document.getElementById('geometry_params_container');
    
    const groups = {
        radius: document.getElementById('group_radius'),
        height: document.getElementById('group_height'),
        length: document.getElementById('group_length')
    };
    
    const inputs = {
        radius: document.getElementById('input_radius'),
        height: document.getElementById('input_height'),
        length: document.getElementById('input_length')
    };

    function updateFields() {
        container.style.display = 'none';
        for (let key in groups) {
            groups[key].style.display = 'none';
            inputs[key].removeAttribute('required');
        }

        if(select.selectedIndex === 0) return;
        
        const slug = select.options[select.selectedIndex].getAttribute('data-slug');
        container.style.display = 'block';

        if (slug === 'spherical') {
            groups.radius.style.display = 'block';
            inputs.radius.setAttribute('required', 'required');
        } else if (slug === 'cubic') {
            groups.length.style.display = 'block';
            inputs.length.setAttribute('required', 'required');
        } else if (slug === 'cylindrical' || slug === 'conical') {
            groups.radius.style.display = 'block';
            inputs.radius.setAttribute('required', 'required');
            groups.height.style.display = 'block';
            inputs.height.setAttribute('required', 'required');
        }
    }

    select.addEventListener('change', updateFields);

    // --------------------------------------------------------
    // 2. Логіка модалки з логом помилок
    // --------------------------------------------------------
    const errorLogModal = document.getElementById('errorLogModal');
    if (errorLogModal) {
        errorLogModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const logContent = button.getAttribute('data-log');
            const modalLogBody = errorLogModal.querySelector('#modal-log-content');
            modalLogBody.textContent = logContent ? logContent : 'Лог помилки порожній.';
        });
    }

    // --------------------------------------------------------
    // 3. Логіка побудови графіка (AJAX + Chart.js)
    // --------------------------------------------------------
    let chartInstance = null;
    const chartModalEl = document.getElementById('chartModal');
    const chartModal = new bootstrap.Modal(chartModalEl);
    
    document.querySelectorAll('.btn-show-chart').forEach(button => {
        button.addEventListener('click', async function() {
            const calcId = this.getAttribute('data-id');
            const geomType = this.getAttribute('data-type');
            
            // Встановлюємо правильну назву осі Х залежно від фігури
            let xAxisLabel = 'Параметр (нм)';
            if (geomType === 'spherical') xAxisLabel = 'Радіус R (нм)';
            else if (geomType === 'cubic') xAxisLabel = 'Довжина ребра L (нм)';
            else if (geomType === 'cylindrical' || geomType === 'conical') xAxisLabel = 'Висота H (нм)';

            const canvas = document.getElementById('energyChart');
            const loading = document.getElementById('chart-loading');
            
            // Скидаємо стан вікна
            canvas.style.display = 'none';
            loading.style.display = 'block';
            loading.innerHTML = '<div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Завантаження даних (AJAX)...</p>';
            
            chartModal.show();

            try {
                // Робимо AJAX запит до CSV файлу
                // URL базується на логіці збереження: /storage/results/calc_{id}/energies_vs_R.csv
                const fileUrl = `/storage/results/calc_${calcId}/energies_vs_R.csv`;
                const response = await fetch(fileUrl);
                
                if (!response.ok) throw new Error('Файл результатів CSV не знайдено на сервері.');
                
                const csvText = await response.text();
                const lines = csvText.trim().split('\n');
                
                if (lines.length < 2) throw new Error('Файл CSV порожній або не містить даних.');

                const headers = lines[0].split(',');
                const xData = [];
                const yDatasets = [];
                
                // Беремо для графіка перші 9 енергетичних рівнів (E0...E9), щоб графік не був перевантаженим
                const numLevelsToPlot = Math.min(headers.length - 1, 14); 
                
                // Кольорова палітра для ліній графіка
                const colors = ['#dc3545', '#0d6efd', '#198754', '#ffc107', '#6f42c1'];

                for (let i = 0; i < numLevelsToPlot; i++) {
                    yDatasets.push({
                        label: headers[i+1].replace('_meV', ''), // Назва лінії (напр., E0)
                        data: [],
                        borderColor: colors[i % colors.length],
                        backgroundColor: colors[i % colors.length],
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        fill: false,
                        tension: 0.1 // Легке згладжування лінії
                    });
                }

                // Парсимо дані по рядках
                for (let i = 1; i < lines.length; i++) {
                    if (!lines[i].trim()) continue;
                    const cols = lines[i].split(',');
                    xData.push(cols[0]); // Вісь X
                    
                    for (let j = 0; j < numLevelsToPlot; j++) {
                        yDatasets[j].data.push(parseFloat(cols[j+1])); // Вісь Y
                    }
                }

                // Ховаємо лоадер, показуємо полотно графіка
                loading.style.display = 'none';
                canvas.style.display = 'block';

                // Видаляємо попередній графік, якщо він був
                if (chartInstance) chartInstance.destroy();

                // Малюємо новий графік
                chartInstance = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: xData,
                        datasets: yDatasets
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Залежність енергії від розміру КТ',
                                font: { size: 16 }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y + ' меВ';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                title: { display: true, text: xAxisLabel, font: { weight: 'bold' } }
                            },
                            y: {
                                title: { display: true, text: 'Енергія (меВ)', font: { weight: 'bold' } }
                            }
                        }
                    }
                });

            } catch (error) {
                console.error(error);
                loading.innerHTML = `<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i> ${error.message}</div>`;
            }
        });
    });
});
</script>
@endsection