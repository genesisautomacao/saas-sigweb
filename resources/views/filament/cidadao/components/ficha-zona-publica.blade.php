{{-- T1.6 (item 3-11) — ficha pública da zona: parâmetros urbanísticos + usos --}}
@php
    $fmt = fn ($v, $suf) => $v !== null ? number_format((float) $v, 2, ',', '.') . ' ' . $suf : '—';

    $grupos = [
        'permitido' => ['label' => 'Usos Permitidos', 'cor' => '#059669', 'fundo' => '#ecfdf5', 'borda' => '#a7f3d0'],
        'permissivel' => ['label' => 'Usos Permissíveis', 'cor' => '#d97706', 'fundo' => '#fffbeb', 'borda' => '#fde68a'],
        'proibido' => ['label' => 'Usos Proibidos', 'cor' => '#dc2626', 'fundo' => '#fef2f2', 'borda' => '#fecaca'],
    ];
@endphp

<div class="space-y-4">
    {{-- Identificação --}}
    <div class="flex items-center gap-3">
        @php
            $rgb = $zona->rgb ? (str_contains($zona->rgb, 'rgb') ? $zona->rgb : "rgb({$zona->rgb})") : 'rgb(150,150,150)';
        @endphp
        <span style="display:inline-block;width:22px;height:22px;border-radius:6px;border:1px solid #d1d5db;background: {{ $rgb }};"></span>
        <div>
            <p class="text-sm font-bold text-gray-800 dark:text-gray-100">{{ $zona->sigla }} — {{ $zona->name }}</p>
            @if ($zona->codigo)
                <p class="text-xs text-gray-500">Código: {{ $zona->codigo }}</p>
            @endif
        </div>
    </div>

    {{-- Parâmetros urbanísticos --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Parâmetros Urbanísticos</p>
        @if ($parametro)
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <p class="text-[10px] text-gray-400 uppercase font-bold">Área Mínima do Lote</p>
                    <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $fmt($parametro->area_minima, 'm²') }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-400 uppercase font-bold">Área Máxima do Lote</p>
                    <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $fmt($parametro->area_maxima, 'm²') }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-400 uppercase font-bold">Testada Mínima</p>
                    <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $fmt($parametro->testada_minima, 'm') }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-gray-400 uppercase font-bold">Testada Máxima</p>
                    <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $fmt($parametro->testada_maxima, 'm') }}</p>
                </div>
            </div>
        @else
            <p class="text-sm text-gray-400 italic">Nenhum parâmetro urbanístico cadastrado para esta zona.</p>
        @endif
    </div>

    {{-- Usos (zoneamento) --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Usos do Solo (por classificação)</p>

        @if ($regras->isEmpty())
            <p class="text-sm text-gray-400 italic">Nenhuma regra de uso cadastrada para esta zona.</p>
        @else
            <div class="space-y-3">
                @foreach ($grupos as $status => $meta)
                    @php $itens = $regras->get($status, collect()); @endphp
                    @if ($itens->isNotEmpty())
                        <div>
                            <p class="text-xs font-bold mb-1" style="color: {{ $meta['cor'] }};">{{ $meta['label'] }}</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($itens as $regra)
                                    <span
                                        style="background: {{ $meta['fundo'] }}; border: 1px solid {{ $meta['borda'] }}; color: {{ $meta['cor'] }};"
                                        class="px-2 py-0.5 rounded-md text-xs font-semibold">
                                        {{ $regra->classificacao }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
            <p class="text-[10px] text-gray-400 mt-3">
                Classificações de uso conforme a legislação municipal de zoneamento. Para a análise de uma atividade
                específica (CNAE), utilize a Consulta de Viabilidade na ficha do imóvel.
            </p>
        @endif
    </div>
</div>
