@php
    // Trilhas antigas podem vir sem properties (null) — colhe vazio, não quebra
    $props = $activity->properties ?? collect();
    $atributos = $props->get('attributes', []);
    $antes = $props->get('old', []);

    // Histórico cartográfico (B13): a geometria é mostrada em mini-mapas, não na tabela.
    $geoAntigo = $antes['geo_json'] ?? null;
    $geoNovo = $atributos['geo_json'] ?? null;
    $temGeo = !empty($geoAntigo) || !empty($geoNovo);

    // Campos "normais" (sem a geometria) para a tabela Antes/Depois — coluna JSON
    // (dados_customizados…) vem EXPLODIDA chave a chave, só o que mudou, com o rótulo
    // do campo do município (AuditoriaDiffService = mesma fonte do PDF/Excel).
    $linhasDiff = \App\Services\Auditoria\AuditoriaDiffService::linhas($activity);
    $fmtValor = fn ($v) => \App\Services\Auditoria\AuditoriaDiffService::formatar($v);

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

    @if(count($linhasDiff) > 0)
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
                        @foreach($linhasDiff as $l)
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-2 text-gray-600">
                                    @if($l['chave'] !== null)
                                        {{-- chave de coluna JSON: rótulo do município + de onde veio --}}
                                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ $l['rotulo'] }}</span>
                                        <span class="block font-mono text-gray-400" style="font-size:10px;">{{ \App\Services\Auditoria\AuditoriaDiffService::rotuloColuna($l['coluna']) }} · {{ $l['chave'] }}</span>
                                    @else
                                        <span class="font-mono">{{ $l['rotulo'] }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-red-500" style="word-break:break-word;">{{ $fmtValor($l['antes']) }}</td>
                                <td class="px-3 py-2 text-green-600" style="word-break:break-word;">{{ $fmtValor($l['depois']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($operacaoBruta === 'updated' && collect($linhasDiff)->contains(fn ($l) => $l['chave'] !== null))
                <p class="mt-1 text-gray-400" style="font-size:11px;">Em campos do município só aparecem as chaves que mudaram nesta operação.</p>
            @endif
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
