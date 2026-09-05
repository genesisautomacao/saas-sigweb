{{-- Ficha SÓ LEITURA da edificação no mapa público (2026-09-05). Renderizada como modalContent
     de MapaPublico::fichaEdificacaoPublicaAction (sem form, sem botão de salvar). Estilos inline:
     o CSS do painel cidadão não tem as classes de cor do Tailwind. --}}
@php
    $fmt = fn ($v, $dec = 2) => $v === null || $v === '' ? null : number_format((float) $v, $dec, ',', '.');
    $linhas = [];
    // `code` pode ser um UUID interno (importação GIS) — só mostra quando for um código legível
    if ($edificacao->code && ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $edificacao->code)) {
        $linhas['Código'] = $edificacao->code;
    }
    if ($lote) {
        $linhas['Lote'] = trim(($lote->numero_lote ? 'Nº '.$lote->numero_lote : '').($quadraNome ? ' · Quadra '.$quadraNome : '')) ?: '#'.$lote->sequential_id;
    }
    $linhas['Área construída (geo)'] = $fmt($edificacao->area_geo) !== null ? $fmt($edificacao->area_geo).' m²' : '—';
    foreach ($campos as $rotulo => $valor) {
        $linhas[$rotulo] = $valor === '-' ? '—' : $valor;
    }
@endphp
<div style="font-size:13px; color:#1f2937;">
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
        <span style="display:inline-flex; align-items:center; gap:6px; background:#fef3c7; color:#92400e; font-weight:700; font-size:11px; padding:2px 8px; border-radius:999px;">&#127968; Edificação #{{ $edificacao->sequential_id ?? $edificacao->id }}</span>
        <span style="font-size:11px; color:#9ca3af;">consulta pública, somente leitura</span>
    </div>

    <div style="border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
        @foreach ($linhas as $rotulo => $valor)
            <div style="display:flex; justify-content:space-between; gap:12px; padding:8px 12px; {{ $loop->first ? '' : 'border-top:1px solid #f3f4f6;' }}">
                <span style="color:#6b7280; flex-shrink:0;">{{ $rotulo }}</span>
                <span style="font-weight:600; text-align:right; color:#111827; word-break:break-word;">{{ $valor }}</span>
            </div>
        @endforeach
    </div>

    @if (empty($campos))
        <p style="font-size:11px; color:#9ca3af; margin:10px 0 0;">Esta prefeitura ainda não cadastrou campos descritivos para edificações.</p>
    @endif
</div>
