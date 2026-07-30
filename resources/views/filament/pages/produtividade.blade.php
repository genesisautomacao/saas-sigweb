<x-filament-panels::page>

    @php
        $dados = $this->dados;
        $r = $dados['resumo'];
        $linhas = $dados['linhas'];
    @endphp

    {{-- Filtros: período + cadastrador --}}
    <div class="flex flex-wrap items-end gap-3 mb-6">
        <div>
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Data inicial</label>
            <input type="date" wire:model.live="dataInicio"
                class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-3 py-1.5 focus:ring-2 focus:ring-primary-500">
        </div>
        <div>
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Data final</label>
            <input type="date" wire:model.live="dataFim"
                class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-3 py-1.5 focus:ring-2 focus:ring-primary-500">
        </div>
        <div>
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Cadastrador</label>
            <select wire:model.live="cadastradorId"
                class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-3 py-1.5 focus:ring-2 focus:ring-primary-500">
                <option value="">Todos os cadastradores</option>
                @foreach ($this->cadastradores as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Cards de resumo (escopo: quadras designadas no período) --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Quadras designadas</p>
            <p class="text-2xl font-bold mt-1">{{ number_format($r['quadras']) }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Lotes na região</p>
            <p class="text-2xl font-bold mt-1">{{ number_format($r['total']) }}</p>
        </div>

        <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-700 p-4 shadow-sm">
            <p class="text-xs text-emerald-600 dark:text-emerald-400 uppercase tracking-wide">Coletados no período</p>
            <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300 mt-1">{{ number_format($r['no_periodo']) }}</p>
        </div>

        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl border border-yellow-200 dark:border-yellow-700 p-4 shadow-sm">
            <p class="text-xs text-yellow-600 dark:text-yellow-400 uppercase tracking-wide">Pendentes</p>
            <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-300 mt-1">{{ number_format($r['pendentes']) }}</p>
        </div>

        <div class="bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-200 dark:border-red-700 p-4 shadow-sm">
            <p class="text-xs text-red-600 dark:text-red-400 uppercase tracking-wide">Inconformidades</p>
            <p class="text-2xl font-bold text-red-700 dark:text-red-300 mt-1">{{ number_format($r['inconformidades']) }}</p>
        </div>

        <div class="bg-primary-50 dark:bg-primary-900/20 rounded-xl border border-primary-200 dark:border-primary-700 p-4 shadow-sm">
            <p class="text-xs text-primary-600 dark:text-primary-400 uppercase tracking-wide">Cumprido</p>
            <p class="text-2xl font-bold text-primary-700 dark:text-primary-300 mt-1">{{ $r['percentual'] }}%</p>
            <div class="mt-2 h-1.5 rounded-full bg-gray-200 dark:bg-gray-600">
                <div class="h-1.5 rounded-full bg-primary-500 transition-all" style="width: {{ $r['percentual'] }}%"></div>
            </div>
        </div>

    </div>

    {{-- Quadras designadas --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h3 class="font-semibold text-sm">Quadras designadas</h3>
            <span class="text-xs text-gray-500">{{ count($linhas) }} registro(s)</span>
        </div>
        <div class="overflow-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Quadra</th>
                        <th class="px-4 py-2 text-left">Cadastrador</th>
                        <th class="px-4 py-2 text-left">Período da atribuição</th>
                        <th class="px-4 py-2 text-right">Lotes</th>
                        <th class="px-4 py-2 text-right">Coletados no período</th>
                        <th class="px-4 py-2 text-right">Coletados (total)</th>
                        <th class="px-4 py-2 text-right">Restantes</th>
                        <th class="px-4 py-2 text-left pl-4">% cumprido</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($linhas as $l)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-4 py-2 font-medium">{{ $l['quadra_nome'] }}</td>
                            <td class="px-4 py-2">{{ $l['cadastrador'] }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $l['periodo'] }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $l['total'] }}</td>
                            <td class="px-4 py-2 text-right">
                                <span class="bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 text-xs px-2 py-0.5 rounded-full">
                                    {{ $l['no_periodo'] }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right text-emerald-600 dark:text-emerald-400">{{ $l['coletados'] }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $l['total'] - $l['coletados'] }}</td>
                            <td class="px-4 py-2 pl-4" style="min-width: 160px;">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-1.5 rounded-full bg-gray-200 dark:bg-gray-600">
                                        <div class="h-1.5 rounded-full {{ $l['percentual'] >= 100 ? 'bg-primary-500' : 'bg-emerald-500' }}"
                                            style="width: {{ min($l['percentual'], 100) }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500 w-12 text-right">{{ $l['percentual'] }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-400 text-xs">
                                Nenhuma região designada no período selecionado.
                                <br>
                                Atribua quadras aos cadastradores em
                                <strong>Coleta cadastral → Regiões dos Cadastradores</strong>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</x-filament-panels::page>
