<x-filament-panels::page>

    @include('filament.partials.pagina-relatorio-css')

    <div wire:poll.60s>
        {{-- Filtro por fluxo --}}
        <div class="pg-filtros">
            <div>
                <label>Filtrar por Fluxo</label>
                <select wire:model.live="fluxoId" style="min-width: 280px;">
                    <option value="">Todos os fluxos</option>
                    @foreach ($this->fluxos as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Cards de resumo --}}
        @php $r = $this->resumo; @endphp
        <div class="pg-cards">
            @foreach ([
                ['Total', $r['total'], ''],
                ['Em andamento', $r['em_andamento'], 'pg-azul'],
                ['Aguardando cidadão', $r['aguardando'], 'pg-amarelo'],
                ['Em correção', $r['pendentes'], 'pg-vermelho'],
                ['Concluídos', $r['concluidos'], 'pg-verde'],
                ['% Concluídos', $r['percentual'] . '%', 'pg-verde'],
            ] as [$label, $valor, $cor])
                <div class="pg-card {{ $cor }}">
                    <p class="pg-t">{{ $label }}</p>
                    <p class="pg-v">{{ $valor }}</p>
                </div>
            @endforeach
        </div>

        {{-- Gráfico de barras por etapa --}}
        <div class="pg-painel">
            <div class="pg-painel-head"><span>Processos por Etapa Atual</span></div>
            <div style="padding: 16px;">

            @if (count($this->porEtapa) === 0)
                <div class="pg-vazio">Nenhum processo em andamento para exibir.</div>
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
