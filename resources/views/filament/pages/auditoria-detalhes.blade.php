@php
    // Trilhas antigas podem vir sem properties (null) — colhe vazio, não quebra
    $props = $activity->properties ?? collect();
    $atributos = $props->get('attributes', []);
    $antes = $props->get('old', []);

    // Histórico cartográfico (B13): a geometria é mostrada em mini-mapas, não na tabela.
    $geoAntigo = $antes['geo_json'] ?? null;
    $geoNovo = $atributos['geo_json'] ?? null;
    $temGeo = !empty($geoAntigo) || !empty($geoNovo);

    // Campos "normais" (sem a geometria) para a tabela Antes/Depois
    $camposTabela = collect($atributos)->except(['geo_json', 'geo']);

    // Valor legível p/ a célula: array/objeto (ex.: dados_customizados) vira
    // JSON — imprimir array cru estourava o htmlspecialchars do Blade.
    $fmtValor = function ($v) {
        if ($v === null || $v === '') {
            return '—';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }

        return is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
    };

    // Operação em português (traduz o "created"/"updated"/"deleted" padrão do Spatie)
    $operacaoBruta = $activity->description ?: $activity->event;
    $operacaoLabel = match ($operacaoBruta) {
        'created' => 'Criado',
        'updated' => 'Atualizado',
        'deleted' => 'Excluído',
        default   => $operacaoBruta,
    };
@endphp

<div class="space-y-4 text-sm">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="font-semibold text-gray-500">Usuário:</span>
            <span class="ml-2">{{ $activity->causer?->name ?? 'Sistema' }}</span>
        </div>
        <div>
            <span class="font-semibold text-gray-500">Data/Hora:</span>
            <span class="ml-2">{{ $activity->created_at->format('d/m/Y H:i:s') }}</span>
        </div>
        <div>
            <span class="font-semibold text-gray-500">Entidade:</span>
            <span class="ml-2">{{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</span>
        </div>
        <div>
            <span class="font-semibold text-gray-500">Operação:</span>
            <span class="ml-2">{{ $operacaoLabel }}</span>
        </div>
    </div>

    @if($camposTabela->isNotEmpty())
        <div class="mt-4">
            <span class="font-semibold text-gray-500">Campos alterados:</span>
            <div class="mt-2 overflow-auto rounded border border-gray-200 dark:border-gray-700">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2">Campo</th>
                            <th class="px-3 py-2">Antes</th>
                            <th class="px-3 py-2">Depois</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($camposTabela as $campo => $valorNovo)
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-2 font-mono text-gray-600">{{ $campo }}</td>
                                <td class="px-3 py-2 text-red-500">{{ $fmtValor($antes[$campo] ?? null) }}</td>
                                <td class="px-3 py-2 text-green-600">{{ $fmtValor($valorNovo) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($temGeo)
        {{-- Croqui do Lote — Antes / Depois (item 044) --}}
        <div style="margin-top:12px;">
            <div class="font-semibold text-gray-500" style="margin-bottom:6px;">Croqui do Lote — Antes / Depois:</div>
            <div wire:ignore
                 x-data="auditoriaGeoMaps({ antigo: @js($geoAntigo), novo: @js($geoNovo) })"
                 x-init="boot()"
                 style="display:flex; gap:12px; width:100%;">
                <div style="flex:1; min-width:0;">
                    <div style="font-size:11px; font-weight:700; color:#dc2626; margin-bottom:4px;">Antes</div>
                    <div x-ref="mapaAntigo" style="width:100%; height:220px; border:1px solid #e5e7eb; border-radius:8px; background:#f3f4f6; overflow:hidden; position:relative;"></div>
                </div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:11px; font-weight:700; color:#16a34a; margin-bottom:4px;">Depois</div>
                    <div x-ref="mapaNovo" style="width:100%; height:220px; border:1px solid #e5e7eb; border-radius:8px; background:#f3f4f6; overflow:hidden; position:relative;"></div>
                </div>
            </div>
        </div>
    @endif
</div>
