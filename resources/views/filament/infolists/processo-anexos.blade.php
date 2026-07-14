@php
    use App\Models\ProcessoAnexo;
    use App\Services\Processo\ProcessoChecklistService;

    $record = $getRecord();
    // PD-1 — 'requerimento_gerado' é artefato intermediário (auditoria no banco, fora da lista):
    // só o requerimento ASSINADO pelo solicitante aparece aqui.
    $anexos = ProcessoAnexo::where('processo_digital_id', $record->id)
        ->where('tipo_anexo', '!=', 'requerimento_gerado')
        ->orderBy('id')
        ->get();

    // PD-2 — checklist de análise por anexo (só uploads do requerente, última versão de cada cadeia)
    $analisaveis = ProcessoChecklistService::anexosAnalisaveis($record);
    $analisaveisIds = $analisaveis->pluck('id');
    $ultimas = ProcessoChecklistService::ultimasVersoes($analisaveis);
    $podeAnalisarAgora = $record->etapaAtual?->executor === 'analista'
        && !in_array($record->status, ['concluido', 'cancelado']);
@endphp

@if ($anexos->isEmpty())
    <span class="text-sm text-gray-500 italic">Nenhum documento anexado.</span>
@else
    <ul class="space-y-3 text-sm">
        @foreach ($anexos as $anexo)
            @php
                $url = \Illuminate\Support\Facades\Storage::url($anexo->caminho_arquivo);
                $ehPdf = str_ends_with(strtolower($anexo->nome_arquivo), '.pdf');
                $ehAnalisavel = $analisaveisIds->contains($anexo->id);
                $ehUltimaVersao = $ultimas->contains($anexo->id);
            @endphp
            <li class="flex items-center gap-2 flex-wrap">
                <a href="{{ $url }}" target="_blank"
                   class="text-primary-600 hover:text-primary-800 underline font-semibold">
                    {{ $ehPdf ? '📕' : '🖼️' }} {{ $anexo->nome_arquivo }}
                </a>

                @if ($anexo->tipo_anexo === 'anotado')
                    <span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">anotado v{{ $anexo->versao }}</span>
                @elseif ($anexo->tipo_anexo === 'requerimento_gerado')
                    <span class="text-xs px-1.5 py-0.5 rounded bg-blue-100 text-blue-800">requerimento gerado v{{ $anexo->versao }}</span>
                @elseif ($anexo->tipo_anexo === 'requerimento_assinado')
                    <span class="text-xs px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-800">assinado v{{ $anexo->versao }}</span>
                @endif

                {{-- PD-2 — status da análise por item --}}
                @if ($ehAnalisavel && !$ehUltimaVersao)
                    <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">substituído</span>
                @elseif ($ehAnalisavel)
                    @if ($anexo->status_analise === 'aprovado')
                        <span class="text-xs px-1.5 py-0.5 rounded bg-green-100 text-green-800"
                              @if($anexo->analisadoPor) title="por {{ $anexo->analisadoPor->name }}" @endif>✔ aprovado</span>
                    @elseif ($anexo->status_analise === 'reprovado')
                        <span class="text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-800"
                              @if($anexo->analisadoPor) title="por {{ $anexo->analisadoPor->name }}" @endif>✖ reprovado</span>
                        @if ($anexo->observacao_analise)
                            <span class="text-xs text-red-700 basis-full pl-6">Motivo: {{ $anexo->observacao_analise }}</span>
                        @endif
                    @else
                        <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">aguardando análise</span>
                    @endif

                    {{-- PD-2 — ações do checklist (só na última versão, com a etapa no analista) --}}
                    @if ($podeAnalisarAgora)
                        @if ($anexo->status_analise !== 'aprovado')
                            <span class="text-gray-400">·</span>
                            <button type="button"
                                    wire:click="aprovarAnexo({{ $anexo->id }})"
                                    class="text-green-600 hover:text-green-800 underline">✔ Aprovar</button>
                        @endif
                        @if ($anexo->status_analise !== 'reprovado')
                            <span class="text-gray-400">·</span>
                            <button type="button"
                                    wire:click="mountAction('reprovarAnexo', { anexoId: {{ $anexo->id }} })"
                                    class="text-red-600 hover:text-red-800 underline">✖ Reprovar</button>
                        @endif
                    @endif
                @endif

                {{-- Item 222 — anotar PDF (abre o editor; salva uma cópia sem sobrescrever) --}}
                @if ($ehPdf)
                    <span class="text-gray-400">·</span>
                    <a href="{{ route('processo-anexo.anotar', $anexo) }}" target="_blank"
                       class="text-amber-600 hover:text-amber-800 underline">✏️ Anotar</a>
                @endif

                {{-- Só cópias ANOTADAS podem ser excluídas (originais do cidadão são preservados) --}}
                @if ($anexo->tipo_anexo === 'anotado')
                    <span class="text-gray-400">·</span>
                    <button type="button"
                            wire:click="excluirAnexoAnotado({{ $anexo->id }})"
                            wire:confirm="Excluir esta cópia anotada? Esta ação não pode ser desfeita."
                            class="text-red-600 hover:text-red-800 underline">🗑️ Excluir</button>
                @endif
            </li>
        @endforeach
    </ul>
@endif
