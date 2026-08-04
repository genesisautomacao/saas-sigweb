{{-- T2.3 (item 3-10) — ficha pública do logradouro: dados + seções com fotos --}}
@php
    $fmtM = fn ($v) => $v !== null ? number_format((float) $v, 0, ',', '.') . ' m' : '—';
@endphp

<div class="space-y-4">
    {{-- Identificação --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="grid grid-cols-3 gap-3">
            <div>
                <p class="text-[10px] text-gray-400 uppercase font-bold">Código</p>
                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $logradouro->codigo ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] text-gray-400 uppercase font-bold">Extensão</p>
                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $fmtM($logradouro->extensao_geo) }}</p>
            </div>
            <div>
                <p class="text-[10px] text-gray-400 uppercase font-bold">Seções</p>
                <p class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $logradouro->secoes->count() }}</p>
            </div>
        </div>
    </div>

    {{-- Seções (item 3-10: "inclusive com as imagens das Seções") --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Seções do Logradouro</p>

        @if ($logradouro->secoes->isEmpty())
            <p class="text-sm text-gray-400 italic">Nenhuma seção cadastrada para este logradouro.</p>
        @else
            <div class="space-y-3">
                @foreach ($logradouro->secoes as $secao)
                    @php
                        $lado = \App\Services\Coleta\CampoDominioService::rotuloValor('secao_logradouro', 'lado', $secao->lado);
                        $pavimentacao = $secao->dados_customizados['tipo_pavimentacao'] ?? null;
                    @endphp
                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-3">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-gray-800 dark:text-gray-100">
                                    {{ $secao->codigo_composto ?? ($secao->name ?: 'Seção #' . $secao->sequential_id) }}
                                </span>
                                @if ($lado)
                                    <span style="background:#ede9fe;border:1px solid #ddd6fe;color:#6d28d9;"
                                        class="px-2 py-0.5 rounded-md text-xs font-semibold">{{ $lado }}</span>
                                @endif
                                @if ($pavimentacao)
                                    <span style="background:#f1f5f9;border:1px solid #e2e8f0;color:#475569;"
                                        class="px-2 py-0.5 rounded-md text-xs font-semibold">{{ ucfirst($pavimentacao) }}</span>
                                @endif
                            </div>
                            <span class="text-xs text-gray-500">{{ $fmtM($secao->extensao_geo) }}</span>
                        </div>

                        {{-- Fotos da seção (T1.7) --}}
                        @if ($secao->fotos->isNotEmpty())
                            <div class="flex gap-2 flex-wrap mt-2">
                                @foreach ($secao->fotos as $foto)
                                    <a href="{{ asset('storage/' . $foto->path) }}" target="_blank"
                                        title="{{ $foto->name }}">
                                        <img src="{{ asset('storage/' . $foto->path) }}" alt="{{ $foto->name }}"
                                            style="width:84px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;" />
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
