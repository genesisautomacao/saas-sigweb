<x-filament-panels::page>

    @php
        $dados = $this->dados;
        $r = $dados['resumo'];
        $linhas = $dados['linhas'];
        $divergencias = $dados['divergencias'];
        $validacao = $this->validacao;
    @endphp

    {{-- Carimbo de validação da campanha (D3) --}}
    @if ($validacao)
        <div class="mb-4 flex items-center gap-3 rounded-xl border border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3">
            <span class="text-2xl">✅</span>
            <div>
                <p class="font-semibold text-emerald-700 dark:text-emerald-300 text-sm">
                    Campanha "{{ $campanha ?? $this->campanha }}" VALIDADA pela prefeitura
                </p>
                <p class="text-xs text-emerald-600 dark:text-emerald-400">
                    por {{ $validacao['nome'] ?? '—' }} em {{ $validacao['validado_em'] ?? '—' }}
                </p>
            </div>
        </div>
    @endif

    {{-- Filtros --}}
    <div class="flex flex-wrap items-end gap-3 mb-6">
        <div>
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Campanha</label>
            <select wire:model.live="campanha"
                class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-3 py-1.5 focus:ring-2 focus:ring-primary-500">
                @foreach ($this->campanhas as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
        </div>
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
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Coletor</label>
            <select wire:model.live="coletorId"
                class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-3 py-1.5 focus:ring-2 focus:ring-primary-500">
                <option value="">Todos</option>
                @foreach ($this->coletores as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 block mb-1">Status</label>
            <select wire:model.live="status"
                class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm px-3 py-1.5 focus:ring-2 focus:ring-primary-500">
                <option value="">Todos</option>
                @foreach (\App\Models\ColetaImobiliaria::STATUS as $chave => $rotulo)
                    <option value="{{ $chave }}">{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Cards de resumo --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Coletas no recorte</p>
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
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Com alterações</p>
            <p class="text-2xl font-bold mt-1">{{ number_format($r['com_alteracoes']) }}</p>
        </div>
        <div class="bg-orange-50 dark:bg-orange-900/20 rounded-xl border border-orange-200 dark:border-orange-700 p-4 shadow-sm">
            <p class="text-xs text-orange-600 dark:text-orange-400 uppercase tracking-wide">Divergências de proprietário</p>
            <p class="text-2xl font-bold text-orange-700 dark:text-orange-300 mt-1">{{ number_format($r['divergencias']) }}</p>
        </div>
    </div>

    {{-- Divergências de proprietário (Frente A4 — decisão do usuário) --}}
    @if ($divergencias !== [])
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-orange-200 dark:border-orange-700 shadow-sm overflow-hidden mb-6">
            <div class="px-4 py-3 border-b border-orange-100 dark:border-orange-800 bg-orange-50/60 dark:bg-orange-900/10 flex items-center justify-between">
                <h3 class="font-semibold text-sm text-orange-700 dark:text-orange-300">⚠️ Divergências de proprietário apontadas em campo</h3>
                <span class="text-xs text-gray-500">{{ count($divergencias) }} unidade(s)</span>
            </div>
            <div class="overflow-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-2 text-left">Lote</th>
                            <th class="px-4 py-2 text-left">Quadra</th>
                            <th class="px-4 py-2 text-left">Inscrição</th>
                            <th class="px-4 py-2 text-left">Proprietário oficial</th>
                            <th class="px-4 py-2 text-left">Informado na coleta</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($divergencias as $d)
                            <tr class="hover:bg-orange-50/40 dark:hover:bg-orange-900/10">
                                <td class="px-4 py-2 font-medium">{{ $d['lote'] }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $d['quadra'] }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $d['inscricao'] }}</td>
                                <td class="px-4 py-2">
                                    {{ $d['oficial_nome'] }}
                                    <span class="block text-xs text-gray-400">{{ $d['oficial_cpf_cnpj'] }}</span>
                                </td>
                                <td class="px-4 py-2 text-orange-700 dark:text-orange-300 font-medium">
                                    {{ $d['divergente_nome'] }}
                                    <span class="block text-xs text-orange-500 font-normal">{{ $d['divergente_cpf_cnpj'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Coletas com detalhe antes→depois --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h3 class="font-semibold text-sm">Coletas realizadas</h3>
            <span class="text-xs text-gray-500">{{ count($linhas) }} registro(s)</span>
        </div>
        <div class="overflow-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Lote</th>
                        <th class="px-4 py-2 text-left">Quadra</th>
                        <th class="px-4 py-2 text-left">Coletor</th>
                        <th class="px-4 py-2 text-left">Quando</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Alterações (antes → depois)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($linhas as $l)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 align-top">
                            <td class="px-4 py-2 font-medium">{{ $l['lote'] }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $l['quadra'] }}</td>
                            <td class="px-4 py-2">{{ $l['coletor'] }}</td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $l['coletado_em'] }}</td>
                            <td class="px-4 py-2">
                                @php
                                    $cores = [
                                        'coletado' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300',
                                        'pendente' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                                        'inconformidade' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                        'nao_visitado' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                                    ];
                                @endphp
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $cores[$l['status']] ?? $cores['nao_visitado'] }}">
                                    {{ $l['status_rotulo'] }}
                                </span>
                                @if ($l['inconformidade'])
                                    <p class="text-xs text-red-600 dark:text-red-400 mt-1 max-w-xs">
                                        {{ $l['inconformidade'] }}
                                        @if ($l['inconformidade_gps'])
                                            <span class="block text-gray-400">📍 {{ $l['inconformidade_gps'] }}</span>
                                        @endif
                                    </p>
                                @endif
                                @if ($l['observacao'])
                                    <p class="text-xs text-gray-500 mt-1 max-w-xs italic">{{ $l['observacao'] }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if ($l['alteracoes'] === [])
                                    <span class="text-xs text-gray-400">—</span>
                                @else
                                    <ul class="space-y-0.5">
                                        @foreach ($l['alteracoes'] as $a)
                                            <li class="text-xs">
                                                <span class="text-gray-400">{{ $a['contexto'] }} ·</span>
                                                <span class="font-medium">{{ $a['campo'] }}:</span>
                                                <span class="text-red-500 line-through">{{ $a['de'] }}</span>
                                                <span class="text-gray-400">→</span>
                                                <span class="text-emerald-600 dark:text-emerald-400 font-medium">{{ $a['para'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-xs">
                                Nenhuma coleta encontrada no recorte selecionado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</x-filament-panels::page>
