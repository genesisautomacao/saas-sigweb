<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcessoDigitalResource\Pages;
use App\Models\ProcessoDigital;
use App\Services\Processo\ProcessoFormService;
use App\Traits\HasTenantModule;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProcessoDigitalResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'processos'; // Um novo módulo!

    protected static ?string $model = ProcessoDigital::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Processos Digitais';

    protected static ?string $modelLabel = 'Processo';

    protected static ?string $pluralModelLabel = 'Caixa de Entrada (Processos)';

    protected static ?int $navigationSort = 3;

    // A prefeitura não cria processo — apenas julga/tramita. Quem abre é o cidadão.
    public static function canCreate(): bool
    {
        return false;
    }

    // Removemos o formulário padrão de "Criar" porque a prefeitura não cria processo, ela apenas julga.
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('info')
                ->content('A visualização completa do processo será construída na próxima etapa.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Escopo por tenant + regra de acesso por setor (item 1)
            ->modifyQueryUsing(function (Builder $query) {
                $query->where('tenant_id', Filament::getTenant()->id)->where('status', '!=', 'rascunho');

                $user = Filament::auth()->user();

                // Master (super-admin) OU permissão explícita 'view_todos_processos' veem tudo.
                // Usa hasPermissionTo() — NÃO can() — de propósito: `can()` passa pelo Gate::before,
                // que dá bypass total ao Manager; assim, retirar a permissão do papel Gerente
                // realmente passa a filtrá-lo por setor.
                if ($user && ($user->hasRole('Master') || $user->hasPermissionTo('view_todos_processos'))) {
                    return $query;
                }

                $uid = $user?->id;
                $meusSetores = $user ? $user->setores()->pluck('setores.id')->all() : [];

                // Vê o processo se: é o analista responsável, OU a etapa atual é de um setor dele
                // e (usuarios_autorizados vazio = todos do setor, ou ele está na lista).
                return $query->where(function (Builder $q) use ($uid, $meusSetores) {
                    $q->where('analista_id', $uid)
                        ->orWhereHas('etapaAtual', function (Builder $e) use ($uid, $meusSetores) {
                            $e->whereIn('setor_id', $meusSetores ?: [-1])
                                ->where(function (Builder $u) use ($uid) {
                                    $u->whereNull('usuarios_autorizados')
                                        ->orWhereRaw("usuarios_autorizados::text = '[]'")
                                        ->orWhereJsonContains('usuarios_autorizados', $uid)
                                        ->orWhereJsonContains('usuarios_autorizados', (string) $uid);
                                });
                        });
                });
            })
            ->columns([
                Tables\Columns\TextColumn::make('codigo_processo')
                    ->label('Protocolo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                // Pesquisa exigida pelo Edital (Nome e E-mail do Requerente)
                Tables\Columns\TextColumn::make('requerente.name')
                    ->label('Solicitante')
                    ->searchable(['users.name', 'users.email'])
                    ->description(fn (ProcessoDigital $record): string => $record->requerente?->email ?? '')
                    ->sortable(),

                // Item 136/147/219 — consultar por telefone (vem da Pessoa vinculada).
                // Oculta por padrão (o usuário mostra pelo seletor de colunas).
                Tables\Columns\TextColumn::make('requerente_telefone')
                    ->label('Telefone')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn (ProcessoDigital $record) => $record->requerente?->pessoaNoTenant($record->tenant_id)?->telefone ?: '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $tenantId = Filament::getTenant()?->id;
                        $userIds = \App\Models\Pessoa::withoutGlobalScopes()
                            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                            ->where('telefone', 'like', "%{$search}%")
                            ->pluck('user_id')
                            ->filter()
                            ->all();

                        return $query->whereIn('requerente_id', $userIds ?: [-1]);
                    }),

                Tables\Columns\TextColumn::make('fluxo.nome')
                    ->label('Serviço')
                    ->wrap()
                    ->sortable(),

                Tables\Columns\TextColumn::make('etapaAtual.nome')
                    ->label('Fase Atual')
                    ->badge()
                    ->wrap()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status Geral')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'em_andamento' => 'Em andamento',
                        'aguardando_solicitante' => 'Aguardando cidadão',
                        'pendente_correcao' => 'Em correção',
                        'concluido' => 'Concluído',
                        'cancelado' => 'Cancelado',
                        default => ucfirst((string) $state),
                    })
                    ->colors([
                        'primary' => 'em_andamento',
                        'info' => 'aguardando_solicitante',
                        'success' => 'concluido',
                        'danger' => ['pendente_correcao', 'cancelado'],
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aberto em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('bpmn_fluxo_id')
                    ->label('Filtrar por Serviço')
                    ->relationship('fluxo', 'nome'),

                // Filtro de status: Em andamento / Concluído
                Tables\Filters\SelectFilter::make('situacao')
                    ->label('Situação')
                    ->options([
                        'andamento' => 'Em andamento',
                        'concluido' => 'Concluído',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return match ($data['value'] ?? null) {
                            'andamento' => $query->whereNotIn('status', ['concluido', 'cancelado']),
                            'concluido' => $query->where('status', 'concluido'),
                            default => $query,
                        };
                    }),

                // Item 137/148 — filtrar/consultar por campos do fluxo (respostas do formulário)
                Tables\Filters\Filter::make('busca_formulario')
                    ->form([
                        Forms\Components\TextInput::make('texto')->label('Buscar nas respostas do formulário'),
                    ])
                    ->query(fn (Builder $query, array $data) => filled($data['texto'] ?? null)
                        ? $query->whereRaw('dados_formulario::text ILIKE ?', ['%'.$data['texto'].'%'])
                        : $query),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()->label('Ver Processo'),

                    // Ação ÚNICA (itens 2/3): preenche o formulário da etapa (com uploads) e aprova/reprova.
                    // Schema e execução compartilhados com o header da View page (avancarProcessoForm/executarAvancarProcesso).
                    Tables\Actions\Action::make('avancar_processo')
                        ->label('Avançar Processo')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->modalHeading('Avançar Processo')
                        ->modalSubmitActionLabel('Confirmar')
                        ->visible(fn (?ProcessoDigital $record) => self::podeAvancarProcesso($record))
                        ->fillForm(fn (?ProcessoDigital $record) => ['dados_formulario' => $record?->dados_formulario ?? []])
                        ->form(fn (?ProcessoDigital $record) => $record === null ? [] : self::avancarProcessoForm($record))
                        ->action(function (ProcessoDigital $record, array $data, Tables\Actions\Action $action) {
                            if (! self::executarAvancarProcesso($record, $data)) {
                                $action->halt(); // guard do checklist — mantém o modal aberto
                            }
                        }),

                    // Abre o mapa em outra aba e navega até o lote do processo (item 1g)
                    // — mesmo padrão do LoteResource ("ver_no_mapa" via focus_lat/focus_lon).
                    Tables\Actions\Action::make('mostrar_mapa')
                        ->label('Mostrar no Mapa')
                        ->icon('heroicon-o-map-pin')
                        ->color('info')
                        ->visible(fn (ProcessoDigital $record) => (bool) $record->lote_id)
                        ->url(function (ProcessoDigital $record) {
                            $lote = \App\Models\Lote::withoutGlobalScopes()->find($record->lote_id);
                            $tenant = Filament::getTenant();
                            if ($lote && $lote->geo_json && isset($lote->geo_json->coordinates[0][0][0])) {
                                $lon = $lote->geo_json->coordinates[0][0][0][0];
                                $lat = $lote->geo_json->coordinates[0][0][0][1];

                                return url('/app/'.$tenant->slug.'/mapa-interativo?layer=lotes&focus_lat='.$lat.'&focus_lon='.$lon);
                            }

                            return null;
                        })
                        ->openUrlInNewTab(),

                    // Impressão do processo em PDF (com brasão) — para acompanhamento
                    Tables\Actions\Action::make('imprimir_pdf')
                        ->label('Imprimir PDF')
                        ->icon('heroicon-o-printer')
                        ->color('gray')
                        ->action(fn (ProcessoDigital $record) => app(\App\Services\Processo\ProcessoDigitalPdfService::class)->gerar($record)),
                ])->tooltip('Ações'),
            ]);
    }

    /** O "Avançar Processo" está disponível? (etapa com o analista e processo ativo). */
    public static function podeAvancarProcesso(?ProcessoDigital $record): bool
    {
        return $record !== null
            && $record->etapaAtual?->executor === 'analista'
            && ! in_array($record->status, ['concluido', 'cancelado']);
    }

    /**
     * Schema do modal "Avançar Processo" — compartilhado entre a ação da tabela (Caixa de
     * Entrada) e o header da View page ("Ver Processo").
     */
    public static function avancarProcessoForm(ProcessoDigital $record): array
    {
        return array_merge(
            [
                // PD-2 — resumo do checklist de documentos (aprovações por item ficam na tela "Ver Processo")
                Forms\Components\Placeholder::make('checklist_resumo')
                    ->label('Checklist de documentos')
                    ->visible(fn () => \App\Services\Processo\ProcessoChecklistService::resumoContadores($record)['total'] > 0)
                    ->content(function () use ($record) {
                        $r = \App\Services\Processo\ProcessoChecklistService::resumoContadores($record);

                        $html = '<div class="text-sm space-y-1">'
                            .'<div>'
                            ."<span class='text-xs px-1.5 py-0.5 rounded bg-green-100 text-green-800'>{$r['aprovados']} aprovado(s)</span> "
                            ."<span class='text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-800'>{$r['reprovados']} reprovado(s)</span> "
                            ."<span class='text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600'>{$r['pendentes']} pendente(s)</span>"
                            .'</div>';

                        foreach ($r['itens_reprovados'] as $item) {
                            $html .= '<div class="text-xs text-red-700">✖ '.e($item->nome_arquivo)
                                .($item->observacao_analise ? ': '.e($item->observacao_analise) : '').'</div>';
                        }

                        if ($r['reprovados'] > 0) {
                            $html .= '<div class="text-xs text-gray-500">Com documentos reprovados, a etapa só pode ser REPROVADA (a lista acima segue junto no parecer).</div>';
                        }

                        return new \Illuminate\Support\HtmlString($html.'</div>');
                    }),

                Forms\Components\Radio::make('decisao')
                    ->label('Decisão')
                    ->options([
                        'aprovado' => 'Aprovar (segue para a próxima etapa)',
                        'reprovado' => 'Reprovar (devolver para uma etapa específica)',
                    ])
                    ->default('aprovado')
                    ->live()
                    ->required(),
            ],
            // Formulário da própria etapa do analista (checklist, uploads solicitados) — opcional
            ProcessoFormService::camposDaEtapa($record->etapaAtual, false, true),
            [
                // Na reprova, o analista escolhe PARA ONDE devolver (ex.: de volta ao cidadão)
                Forms\Components\Select::make('etapa_destino_id')
                    ->label('Retornar para qual etapa?')
                    ->options(\App\Models\BpmnEtapa::where('bpmn_fluxo_id', $record->bpmn_fluxo_id)
                        ->where('id', '!=', $record->etapa_atual_id)
                        ->orderBy('ordem')->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn ($e) => [$e->id => "#{$e->ordem} — {$e->nome} (".($e->executor === 'solicitante' ? 'Cidadão' : 'Setor').')'])
                        ->toArray())
                    ->searchable()
                    ->visible(fn (\Filament\Forms\Get $get) => $get('decisao') === 'reprovado')
                    ->required(fn (\Filament\Forms\Get $get) => $get('decisao') === 'reprovado')
                    ->helperText('Quando essa etapa resolver a pendência, o processo volta automaticamente para você (quem reprovou).'),

                Forms\Components\Textarea::make('parecer')
                    ->label('Parecer Técnico / Justificativa')
                    ->helperText('Ex.: justifique a reprovação ou registre o parecer da aprovação.')
                    ->required()
                    ->rows(4),
            ],
        );
    }

    /**
     * Executa o julgamento do modal "Avançar Processo".
     * Retorna false quando o guard do checklist barra a aprovação — o chamador deve dar
     * $action->halt() para manter o modal aberto.
     */
    public static function executarAvancarProcesso(ProcessoDigital $record, array $data): bool
    {
        $etapa = $record->etapaAtual;
        if (! $etapa) {
            return true;
        }

        // PD-2 — guard: não se aprova a etapa com documentos reprovados no checklist.
        if ($data['decisao'] === 'aprovado'
            && \App\Services\Processo\ProcessoChecklistService::reprovadosPendentes($record)->isNotEmpty()) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('Há documentos reprovados no checklist')
                ->body('Reprove a etapa devolvendo ao cidadão, ou reavalie os documentos na seção "Documentos e PDFs" antes de aprovar.')
                ->send();

            return false;
        }

        // 1. Salva o que o analista preencheu/anexou nesta etapa (se houver)
        $chave = ProcessoFormService::chaveEtapa($etapa->id);
        $novos = data_get($data, "dados_formulario.{$chave}", []);
        if (! empty($novos)) {
            $dados = $record->dados_formulario ?? [];
            $dados[$chave] = $novos;
            $record->update(['dados_formulario' => $dados]);
            ProcessoFormService::salvarRespostaEtapa($record, $etapa, Filament::auth()->id());
        }

        // PD-2 — na reprova, a lista de itens reprovados vai junto no parecer
        // (flui para a tramitação, o aviso de correção e o e-mail do cidadão).
        $parecer = $data['parecer'] ?? null;
        if ($data['decisao'] !== 'aprovado'
            && ($sufixo = \App\Services\Processo\ProcessoChecklistService::resumoReprovados($record))) {
            $parecer = trim(($parecer ? $parecer."\n\n" : '').$sufixo);
        }

        // 2. Julga: aprovar avança (ou volta ao reprovador, se havia retorno);
        //    reprovar devolve para a etapa escolhida e marca o retorno.
        ProcessoFormService::julgarEtapa($record, Filament::auth()->id(), $data['decisao'], $parecer, $data['etapa_destino_id'] ?? null);

        \Filament\Notifications\Notification::make()
            ->success()
            ->title(match (true) {
                $record->status === 'concluido' => 'Processo finalizado com sucesso!',
                $data['decisao'] === 'aprovado' => 'Processo avançado.',
                default => 'Processo devolvido para a etapa escolhida.',
            })
            ->send();

        return true;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessoDigitals::route('/'),
            'view' => Pages\ViewProcessoDigital::route('/{record}'),
        ];
    }
}
