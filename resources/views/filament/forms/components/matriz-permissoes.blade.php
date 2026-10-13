@php
    /** @var array $linhas  linhas da matriz (RoleResource::matrizDoModulo) */
    /** @var array $colunas coluna => rótulo */
    /** @var bool $somenteLeitura papel que acompanha todos os módulos / Manager: caixas travadas */
    $somenteLeitura = (bool) ($somenteLeitura ?? false);
    $travaAttr = $somenteLeitura ? 'disabled' : '';
    $porColuna = [];
    foreach ($colunas as $col => $rotulo) {
        $porColuna[$col] = array_values(array_filter(array_map(fn ($l) => $l[$col] ?? null, $linhas)));
    }
    $titulos = [
        'menu' => 'Ver a tela no menu (lista/cadastro). Permissão única "gerenciar" libera a tela inteira.',
        'mapa' => 'Ver a camada no mapa interativo.',
        'criar' => 'Criar no mapa (desenho) e na tela.',
        'editar' => 'Editar dados e geometria no mapa e na tela.',
        'excluir' => 'Excluir no mapa e na tela.',
    ];
@endphp

{{-- R83 — matriz Menu · Mapa · Criar · Editar · Excluir por entidade do módulo.
     O estado é a lista de permissões marcadas (mesmo formato do CheckboxList), então
     Create/EditRole tratam esta caixa igual às outras. Cores/medidas em style inline
     (o painel usa só o CSS pré-compilado do Filament). --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            lista() { return Array.isArray(this.state) ? this.state : []; },
            tem(p) { return this.lista().includes(p); },
            alternar(p) {
                const l = this.lista();
                this.state = this.tem(p) ? l.filter(x => x !== p) : [...l, p];
            },
            todos(perms) { return perms.length > 0 && perms.every(p => this.tem(p)); },
            alternarTodos(perms) {
                const l = this.lista();
                this.state = this.todos(perms) ? l.filter(x => !perms.includes(x)) : Array.from(new Set([...l, ...perms]));
            },
        }"
        style="overflow-x:auto;{{ $somenteLeitura ? ' opacity:.6; pointer-events:none;' : '' }}"
        @if ($somenteLeitura) title="Papel que acompanha todos os módulos: recebe tudo automaticamente — caixas só informativas" @endif
    >
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="border-bottom:1px solid rgba(128,128,128,.3);">
                    <th style="text-align:left; padding:6px 8px; font-weight:600; opacity:.8;">Entidade</th>
                    @foreach ($colunas as $col => $rotulo)
                        <th style="text-align:center; padding:6px 8px; font-weight:600; opacity:.8; white-space:nowrap;" title="{{ $titulos[$col] ?? '' }}">
                            {{ $rotulo }}
                            @if (count($porColuna[$col]) > 1)
                                <br>
                                <input type="checkbox" {{ $travaAttr }} class="fi-checkbox-input" style="width:14px; height:14px; cursor:pointer;"
                                    title="Marcar / desmarcar a coluna inteira"
                                    :checked="todos(@js($porColuna[$col]))" @change="alternarTodos(@js($porColuna[$col]))">
                            @endif
                        </th>
                    @endforeach
                    <th style="text-align:center; padding:6px 8px; font-weight:600; opacity:.8; white-space:nowrap;" title="Marcar / desmarcar a linha inteira">Tudo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($linhas as $linha)
                    @php
                        $permsLinha = array_values(array_filter(array_map(fn ($col) => $linha[$col] ?? null, array_keys($colunas))));
                    @endphp
                    <tr style="border-bottom:1px solid rgba(128,128,128,.15);">
                        <td style="padding:6px 8px;">
                            <span style="font-weight:500;">{{ $linha['label'] }}</span>
                            @if (! empty($linha['nota']))
                                <br><span style="font-size:11px; opacity:.65;">{{ $linha['nota'] }}</span>
                            @endif
                        </td>
                        @foreach ($colunas as $col => $rotulo)
                            <td style="text-align:center; padding:6px 8px;">
                                @if (! empty($linha[$col]))
                                    <input type="checkbox" {{ $travaAttr }} class="fi-checkbox-input" style="width:16px; height:16px; cursor:pointer;"
                                        title="{{ $linha[$col] }}"
                                        :checked="tem('{{ $linha[$col] }}')" @change="alternar('{{ $linha[$col] }}')">
                                @elseif ($col === 'mapa' && ! empty($linha['mapa_de']))
                                    <span style="font-size:11px; opacity:.65;" title="A camada do mapa é a mesma de {{ $linha['mapa_de'] }}">segue {{ $linha['mapa_de'] }}</span>
                                @else
                                    <span style="opacity:.35;">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td style="text-align:center; padding:6px 8px;">
                            @if (count($permsLinha) > 1)
                                <input type="checkbox" {{ $travaAttr }} class="fi-checkbox-input" style="width:14px; height:14px; cursor:pointer;"
                                    title="Marcar / desmarcar a linha inteira"
                                    :checked="todos(@js($permsLinha))" @change="alternarTodos(@js($permsLinha))">
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-dynamic-component>
