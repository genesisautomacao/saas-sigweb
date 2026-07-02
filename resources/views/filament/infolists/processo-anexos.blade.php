@php
    $record = $getRecord();
    $anexos = \App\Models\ProcessoAnexo::where('processo_digital_id', $record->id)->orderBy('id')->get();
@endphp

@if ($anexos->isEmpty())
    <span class="text-sm text-gray-500 italic">Nenhum documento anexado.</span>
@else
    <ul class="space-y-3 text-sm">
        @foreach ($anexos as $anexo)
            @php
                $url = \Illuminate\Support\Facades\Storage::url($anexo->caminho_arquivo);
                $ehPdf = str_ends_with(strtolower($anexo->nome_arquivo), '.pdf');
            @endphp
            <li class="flex items-center gap-2 flex-wrap">
                <a href="{{ $url }}" target="_blank"
                   class="text-primary-600 hover:text-primary-800 underline font-semibold">
                    {{ $ehPdf ? '📕' : '🖼️' }} {{ $anexo->nome_arquivo }}
                </a>

                @if ($anexo->tipo_anexo === 'anotado')
                    <span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">anotado v{{ $anexo->versao }}</span>
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
