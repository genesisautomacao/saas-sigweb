<x-filament-panels::page>

    @include('filament.partials.pagina-relatorio-css')

    @php
        $dados = $this->dados;
        $r = $dados['resumo'];
        $linhas = $dados['linhas'];
        $divergencias = $dados['divergencias'];
        $validacao = $this->validacao;
        $badgeStatus = [
            'coletado' => 'pg-b-verde',
            'pendente' => 'pg-b-amarelo',
            'inconformidade' => 'pg-b-vermelho',
            'nao_visitado' => 'pg-b-cinza',
        ];
    @endphp

    {{-- Carimbo de validação da campanha (D3) --}}
    @if ($validacao)
        <div class="pg-carimbo">
            <span style="font-size: 22px;">✅</span>
            <div>
                <strong>Campanha "{{ $campanha }}" VALIDADA pela prefeitura</strong>
                <span>por {{ $validacao['nome'] ?? '—' }} em {{ $validacao['validado_em'] ?? '—' }}</span>
            </div>
        </div>
    @endif

    {{-- Filtros --}}
    <div class="pg-filtros">
        <div>
            <label>Campanha</label>
            <select wire:model.live="campanha">
                @foreach ($this->campanhas as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Data inicial</label>
            <input type="date" wire:model.live="dataInicio">
        </div>
        <div>
            <label>Data final</label>
            <input type="date" wire:model.live="dataFim">
        </div>
        <div>
            <label>Coletor</label>
            <select wire:model.live="coletorId">
                <option value="">Todos os coletores</option>
                @foreach ($this->coletores as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Status</label>
            <select wire:model.live="status">
                <option value="">Todos os status</option>
                @foreach (\App\Models\ColetaImobiliaria::STATUS as $chave => $rotulo)
                    <option value="{{ $chave }}">{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Cards de resumo --}}
    <div class="pg-cards">
        <div class="pg-card">
            <p class="pg-t">Coletas no recorte</p>
            <p class="pg-v">{{ number_format($r['total']) }}</p>
        </div>
        <div class="pg-card pg-verde">
            <p class="pg-t">Coletados</p>
            <p class="pg-v">{{ number_format($r['coletados']) }}</p>
        </div>
        <div class="pg-card pg-amarelo">
            <p class="pg-t">Pendentes</p>
            <p class="pg-v">{{ number_format($r['pendentes']) }}</p>
        </div>
        <div class="pg-card pg-vermelho">
            <p class="pg-t">Inconformidades</p>
            <p class="pg-v">{{ number_format($r['inconformidades']) }}</p>
        </div>
        <div class="pg-card">
            <p class="pg-t">Com alterações</p>
            <p class="pg-v">{{ number_format($r['com_alteracoes']) }}</p>
        </div>
        <div class="pg-card pg-laranja">
            <p class="pg-t">Divergências de proprietário</p>
            <p class="pg-v">{{ number_format($r['divergencias']) }}</p>
        </div>
    </div>

    {{-- Divergências de proprietário (Frente A4 — decisão do usuário) --}}
    @if ($divergencias !== [])
        <div class="pg-painel pg-borda-laranja">
            <div class="pg-painel-head">
                <span style="color: #c2410c;">⚠️ Divergências de proprietário apontadas em campo</span>
                <span class="pg-sub">{{ count($divergencias) }} unidade(s)</span>
            </div>
            <div class="pg-scroll">
                <table class="pg-table">
                    <thead>
                        <tr>
                            <th>Lote</th>
                            <th>Quadra</th>
                            <th>Inscrição</th>
                            <th>Proprietário oficial</th>
                            <th>Informado na coleta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($divergencias as $d)
                            <tr>
                                <td style="font-weight: 600;">{{ $d['lote'] }}</td>
                                <td style="color: #6b7280;">{{ $d['quadra'] }}</td>
                                <td style="font-size: 12px; color: #6b7280;">{{ $d['inscricao'] }}</td>
                                <td>
                                    {{ $d['oficial_nome'] }}
                                    <span style="display: block; font-size: 12px; color: #9ca3af;">{{ $d['oficial_cpf_cnpj'] }}</span>
                                </td>
                                <td style="color: #c2410c; font-weight: 600;">
                                    {{ $d['divergente_nome'] }}
                                    <span style="display: block; font-size: 12px; color: #ea580c; font-weight: 400;">{{ $d['divergente_cpf_cnpj'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Coletas com detalhe antes→depois --}}
    <div class="pg-painel">
        <div class="pg-painel-head">
            <span>Coletas realizadas</span>
            <span class="pg-sub">{{ count($linhas) }} registro(s)</span>
        </div>
        <div class="pg-scroll">
            <table class="pg-table">
                <thead>
                    <tr>
                        <th>Lote</th>
                        <th>Quadra</th>
                        <th>Coletor</th>
                        <th>Quando</th>
                        <th>Status</th>
                        <th>Alterações (antes → depois)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($linhas as $l)
                        <tr>
                            <td style="font-weight: 600;">{{ $l['lote'] }}</td>
                            <td style="color: #6b7280;">{{ $l['quadra'] }}</td>
                            <td>{{ $l['coletor'] }}</td>
                            <td style="font-size: 12px; color: #6b7280; white-space: nowrap;">{{ $l['coletado_em'] }}</td>
                            <td>
                                <span class="pg-badge {{ $badgeStatus[$l['status']] ?? 'pg-b-cinza' }}">{{ $l['status_rotulo'] }}</span>
                                @if ($l['inconformidade'])
                                    <p style="font-size: 12px; color: #b91c1c; margin-top: 5px; max-width: 260px;">
                                        {{ $l['inconformidade'] }}
                                        @if ($l['inconformidade_gps'])
                                            <span style="display: block; color: #9ca3af;">📍 {{ $l['inconformidade_gps'] }}</span>
                                        @endif
                                    </p>
                                @endif
                                @if ($l['observacao'])
                                    <p style="font-size: 12px; color: #6b7280; margin-top: 5px; max-width: 260px; font-style: italic;">{{ $l['observacao'] }}</p>
                                @endif
                            </td>
                            <td>
                                @if ($l['alteracoes'] === [])
                                    <span style="color: #9ca3af; font-size: 12px;">—</span>
                                @else
                                    <ul style="margin: 0; padding: 0; list-style: none;">
                                        @foreach ($l['alteracoes'] as $a)
                                            <li style="font-size: 12.5px; margin-bottom: 3px;">
                                                <span style="color: #9ca3af;">{{ $a['contexto'] }} ·</span>
                                                <span style="font-weight: 600;">{{ $a['campo'] }}:</span>
                                                <span style="color: #dc2626; text-decoration: line-through;">{{ $a['de'] }}</span>
                                                <span style="color: #9ca3af;">→</span>
                                                <span style="color: #059669; font-weight: 600;">{{ $a['para'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="pg-vazio">Nenhuma coleta encontrada no recorte selecionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</x-filament-panels::page>
