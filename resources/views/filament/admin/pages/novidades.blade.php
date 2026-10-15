<x-filament-panels::page>
    @if (empty($releases))
        <x-filament::section>
            Nenhuma página de novidades publicada ainda (pasta <code>public/novidades/</code>).
        </x-filament::section>
    @else
        <x-filament::section>
            <div style="display:flex; flex-direction:column;">
                @foreach ($releases as $i => $r)
                    <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap; padding:14px 4px; {{ $i > 0 ? 'border-top:1px solid rgba(128,128,128,.2);' : '' }}">
                        <div style="min-width:72px;">
                            <x-filament::badge :color="$i === 0 ? 'primary' : 'gray'">Release {{ $r['release'] }}</x-filament::badge>
                        </div>
                        <div style="flex:1; min-width:220px;">
                            <div style="font-weight:600;">{{ $r['resumo'] ?: $r['titulo'] }}</div>
                            <div style="font-size:13px; opacity:.7;">
                                {{ $r['data'] ? \Illuminate\Support\Carbon::parse($r['data'])->format('d/m/Y') : 'sem data' }}
                                @if ($i === 0) · mais recente @endif
                            </div>
                        </div>
                        <x-filament::button tag="a" :href="$r['url']" target="_blank" icon="heroicon-o-eye" size="sm">
                            Visualizar
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
