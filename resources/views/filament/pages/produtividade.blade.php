<x-filament-panels::page>

    @include('filament.partials.pagina-relatorio-css')

    @php
        $dados = $this->dados;
        $r = $dados['resumo'];
        $linhas = $dados['linhas'];
    @endphp

    {{-- Filtros: período + cadastrador --}}
    <div class="pg-filtros">
        <div>
            <label>Data inicial</label>
            <input type="date" wire:model.live="dataInicio">
        </div>
        <div>
            <label>Data final</label>
            <input type="date" wire:model.live="dataFim">
        </div>
        <div>
            <label>Cadastrador</label>
            <select wire:model.live="cadastradorId">
                <option value="">Todos os cadastradores</option>
                @foreach ($this->cadastradores as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Cards de resumo (escopo: quadras designadas no período) --}}
    <div class="pg-cards">
        <div class="pg-card">
            <p class="pg-t">Quadras designadas</p>
            <p class="pg-v">{{ number_format($r['quadras']) }}</p>
        </div>
        <div class="pg-card">
            <p class="pg-t">Lotes na região</p>
            <p class="pg-v">{{ number_format($r['total']) }}</p>
        </div>
        <div class="pg-card pg-verde">
            <p class="pg-t">Coletados no período</p>
            <p class="pg-v">{{ number_format($r['no_periodo']) }}</p>
        </div>
        <div class="pg-card pg-amarelo">
            <p class="pg-t">Pendentes</p>
            <p class="pg-v">{{ number_format($r['pendentes']) }}</p>
        </div>
        <div class="pg-card pg-vermelho">
            <p class="pg-t">Inconformidades</p>
            <p class="pg-v">{{ number_format($r['inconformidades']) }}</p>
        </div>
        <div class="pg-card pg-azul">
            <p class="pg-t">Atendido</p>
            <p class="pg-v">{{ $r['percentual'] }}%</p>
            <div class="pg-barra" style="margin-top: 8px;">
                <div class="pg-trilho"><div class="pg-preenchido" style="width: {{ min($r['percentual'], 100) }}%; background: #1d4ed8;"></div></div>
            </div>
        </div>
    </div>

    {{-- Quadras designadas --}}
    <div class="pg-painel">
        <div class="pg-painel-head">
            <span>Quadras designadas</span>
            <span class="pg-sub">{{ count($linhas) }} registro(s)</span>
        </div>
        <div class="pg-scroll">
            <table class="pg-table">
                <thead>
                    <tr>
                        <th>Quadra</th>
                        <th>Cadastrador</th>
                        <th>Período da atribuição</th>
                        <th style="text-align: right;">Lotes</th>
                        <th style="text-align: right;">Coletados no período</th>
                        <th style="text-align: right;">Coletados (total)</th>
                        <th style="text-align: right;">Restantes</th>
                        <th>% atendido</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($linhas as $l)
                        <tr>
                            <td style="font-weight: 600;">{{ $l['quadra_nome'] }}</td>
                            <td>{{ $l['cadastrador'] }}</td>
                            <td style="font-size: 12px; color: #6b7280;">{{ $l['periodo'] }}</td>
                            <td style="text-align: right; color: #6b7280;">{{ $l['total'] }}</td>
                            <td style="text-align: right;">
                                <span class="pg-badge pg-b-verde">{{ $l['no_periodo'] }}</span>
                            </td>
                            <td style="text-align: right; color: #059669;">{{ $l['coletados'] }}</td>
                            {{-- Restantes = ainda não atendidos (coletado + inconformidade contam como visita feita) --}}
                            <td style="text-align: right; color: #6b7280;">{{ $l['total'] - $l['atendidos'] }}</td>
                            <td>
                                <div class="pg-barra">
                                    <div class="pg-trilho">
                                        <div class="pg-preenchido" style="width: {{ min($l['percentual'], 100) }}%; {{ $l['percentual'] >= 100 ? 'background:#1d4ed8;' : '' }}"></div>
                                    </div>
                                    <span class="pg-pct">{{ $l['percentual'] }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="pg-vazio">
                                Nenhuma região designada no período selecionado.
                                <br>
                                Atribua quadras aos cadastradores em <strong>Coleta cadastral → Regiões dos Cadastradores</strong>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</x-filament-panels::page>
