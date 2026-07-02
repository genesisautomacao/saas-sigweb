<x-filament-panels::page>
    <div wire:poll.60s>
        {{-- Filtro por fluxo --}}
        <div class="mb-6 max-w-md">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Filtrar por Fluxo</label>
            <select wire:model.live="fluxoId"
                class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 shadow-sm">
                <option value="">Todos os fluxos</option>
                @foreach ($this->fluxos as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </div>

        {{-- Cards de resumo --}}
        @php $r = $this->resumo; @endphp
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            @foreach ([
                ['Total', $r['total'], 'text-gray-700 dark:text-gray-200'],
                ['Em andamento', $r['em_andamento'], 'text-blue-600'],
                ['Aguardando cidadão', $r['aguardando'], 'text-sky-600'],
                ['Em correção', $r['pendentes'], 'text-red-600'],
                ['Concluídos', $r['concluidos'], 'text-green-600'],
                ['% Concluídos', $r['percentual'] . '%', 'text-emerald-600'],
            ] as [$label, $valor, $cor])
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 shadow-sm">
                    <div class="text-xs text-gray-500 mb-1">{{ $label }}</div>
                    <div class="text-2xl font-bold {{ $cor }}">{{ $valor }}</div>
                </div>
            @endforeach
        </div>

        {{-- Gráfico de barras por etapa --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 shadow-sm">
            <div class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Processos por Etapa Atual</div>

            @if (count($this->porEtapa) === 0)
                <div class="text-sm text-gray-500 italic py-8 text-center">Nenhum processo em andamento para exibir.</div>
            @else
                <div
                    wire:ignore
                    x-data="reurbProgressoChart(@js($this->porEtapa))"
                    x-init="render()"
                    @if (true) wire:key="chart-{{ $fluxoId ?? 'all' }}-{{ count($this->porEtapa) }}" @endif
                >
                    <div style="height: 340px;"><canvas x-ref="canvas"></canvas></div>
                </div>
            @endif
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        function reurbProgressoChart(dados) {
            return {
                chart: null,
                render() {
                    const labels = dados.map(d => d.etapa);
                    const valores = dados.map(d => d.total);
                    const cores = dados.map(d => d.cor);

                    if (this.chart) { this.chart.destroy(); }

                    this.chart = new Chart(this.$refs.canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Processos',
                                data: valores,
                                backgroundColor: cores,
                                borderRadius: 6,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                        }
                    });
                }
            }
        }
    </script>
</x-filament-panels::page>
