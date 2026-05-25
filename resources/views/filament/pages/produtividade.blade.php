<x-filament-panels::page>

    {{-- Filtros --}}
    <div class="flex flex-wrap gap-3 mb-6">
        <div>
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Data base</label>
            <input type="date" wire:model.live="dataFiltro"
                class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-3 py-1.5 focus:ring-2 focus:ring-primary-500">
        </div>
    </div>

    {{-- Cards de resumo --}}
    @php $r = $this->resumo; @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total</p>
            <p class="text-2xl font-bold mt-1">{{ number_format($r['total']) }}</p>
        </div>

        <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-700 p-4 shadow-sm">
            <p class="text-xs text-emerald-600 dark:text-emerald-400 uppercase tracking-wide">Coletados</p>
            <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300 mt-1">{{ number_format($r['coletados']) }}</p>
        </div>

        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl border border-yellow-200 dark:border-yellow-700 p-4 shadow-sm">
            <p class="text-xs text-yellow-600 dark:text-yellow-400 uppercase tracking-wide">Pendentes</p>
            <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-300 mt-1">{{ number_format($r['pendentes']) }}</p>
        </div>

        <div class="bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-200 dark:border-red-700 p-4 shadow-sm">
            <p class="text-xs text-red-600 dark:text-red-400 uppercase tracking-wide">Inconformidades</p>
            <p class="text-2xl font-bold text-red-700 dark:text-red-300 mt-1">{{ number_format($r['inconformidades']) }}</p>
        </div>

        <div class="bg-gray-50 dark:bg-gray-700/40 rounded-xl border border-gray-200 dark:border-gray-600 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Não visitados</p>
            <p class="text-2xl font-bold text-gray-600 dark:text-gray-300 mt-1">{{ number_format($r['nao_visitados']) }}</p>
        </div>

        <div class="bg-primary-50 dark:bg-primary-900/20 rounded-xl border border-primary-200 dark:border-primary-700 p-4 shadow-sm">
            <p class="text-xs text-primary-600 dark:text-primary-400 uppercase tracking-wide">Progresso</p>
            <p class="text-2xl font-bold text-primary-700 dark:text-primary-300 mt-1">{{ $r['percentual'] }}%</p>
            <div class="mt-2 h-1.5 rounded-full bg-gray-200 dark:bg-gray-600">
                <div class="h-1.5 rounded-full bg-primary-500 transition-all" style="width: {{ $r['percentual'] }}%"></div>
            </div>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Tabela por cadastrador --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <h3 class="font-semibold text-sm">Por Cadastrador</h3>
            </div>
            <div class="overflow-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2 text-left">Nome</th>
                            <th class="px-4 py-2 text-right">Hoje</th>
                            <th class="px-4 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($this->porCadastrador as $c)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-2 font-medium">{{ $c['name'] }}</td>
                                <td class="px-4 py-2 text-right">
                                    <span class="bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 text-xs px-2 py-0.5 rounded-full">
                                        {{ $c['coletados_hoje'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-400">{{ $c['coletados_total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-gray-400 text-xs">Nenhum dado ainda</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tabela por quadra --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <h3 class="font-semibold text-sm">Por Quadra (top 20)</h3>
            </div>
            <div class="overflow-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2 text-left">Quadra</th>
                            <th class="px-4 py-2 text-right">Coletados</th>
                            <th class="px-4 py-2 text-right">Total</th>
                            <th class="px-4 py-2 text-left pl-4">Progresso</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($this->porQuadra as $q)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-4 py-2 font-medium">{{ $q['quadra_nome'] }}</td>
                                <td class="px-4 py-2 text-right text-emerald-600 dark:text-emerald-400">{{ $q['coletados'] }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ $q['total'] }}</td>
                                <td class="px-4 py-2 pl-4">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-1.5 rounded-full bg-gray-200 dark:bg-gray-600">
                                            <div class="h-1.5 rounded-full bg-emerald-500" style="width: {{ $q['percentual'] ?? 0 }}%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500 w-10 text-right">{{ $q['percentual'] ?? 0 }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-400 text-xs">Nenhum dado ainda</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-filament-panels::page>
