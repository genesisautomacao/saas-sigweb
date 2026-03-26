<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcessoDigitalResource\Pages;
use App\Models\ProcessoDigital;
use App\Models\BpmnEtapa;
use App\Models\ProcessoTramitacao;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Facades\Filament;
use App\Traits\HasTenantModule;

class ProcessoDigitalResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'processos'; // Um novo módulo!

    protected static ?string $model = ProcessoDigital::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';
    protected static ?string $navigationGroup = 'Processos Digitais';
    protected static ?string $modelLabel = 'Processo';
    protected static ?string $pluralModelLabel = 'Caixa de Entrada (Processos)';
    protected static ?int $navigationSort = 1;

    // Removemos o formulário padrão de "Criar" porque a prefeitura não cria processo, ela apenas julga.
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('info')
                ->content('A visualização completa do processo será construída na próxima etapa.')
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // 🛑 Garante que o analista só veja os processos da cidade dele (Multi-Tenant)
            ->modifyQueryUsing(fn (Builder $query) => $query->where('tenant_id', Filament::getTenant()->id)->where('status', '!=', 'rascunho'))
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

                Tables\Columns\TextColumn::make('fluxo.nome')
                    ->label('Serviço')
                    ->sortable(),

                Tables\Columns\TextColumn::make('etapaAtual.nome')
                    ->label('Fase Atual')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status Geral')
                    ->badge()
                    ->colors([
                        'primary' => 'em_andamento',
                        'success' => 'concluido',
                        'danger' => 'cancelado',
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
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Analisar'),
                
                // 🛑 O MOTOR DE TRAMITAÇÃO EXIGIDO PELA PROVA DE CONCEITO
                Tables\Actions\Action::make('tramitar')
                    ->label('Tramitar / Julgar')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->form(fn (ProcessoDigital $record) => [
                        Forms\Components\Select::make('status_parecer')
                            ->label('Decisão do Analista')
                            ->options([
                                'aprovado' => 'Aprovado (Avançar Fase)',
                                'encaminhado' => 'Apenas Encaminhar',
                                'reprovado' => 'Reprovado (Devolver para Correção)',
                                'concluido' => '🌟 DEFERIDO / CONCLUIR PROCESSO', // 🛑 NOVA OPÇÃO
                            ])
                            ->reactive()
                            ->required(),

                        Forms\Components\Select::make('etapa_destino_id')
                            ->label('Para qual etapa deseja enviar?')
                            ->options(BpmnEtapa::where('bpmn_fluxo_id', $record->bpmn_fluxo_id)->pluck('nome', 'id'))
                            // 🛑 Se for concluído, não exige próxima etapa!
                            ->required(fn (\Filament\Forms\Get $get) => $get('status_parecer') !== 'concluido')
                            ->hidden(fn (\Filament\Forms\Get $get) => $get('status_parecer') === 'concluido'),

                        Forms\Components\Select::make('usuario_destino_id')
                            ->label('Encaminhar para um Analista Específico?')
                            ->options(User::whereHas('tenants', fn($q) => $q->where('tenants.id', Filament::getTenant()->id))->pluck('name', 'id'))
                            ->searchable()
                            ->helperText('Deixe em branco para enviar para a fila geral da etapa (Qualquer analista poderá pegar).'),

                        Forms\Components\Textarea::make('parecer')
                            ->label('Parecer Técnico / Despacho')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (ProcessoDigital $record, array $data) {
                        // 🛑 A VACINA: Se o campo foi escondido (Concluído), força a variável a ser null
                        $etapaDestinoId = $data['status_parecer'] === 'concluido' ? null : ($data['etapa_destino_id'] ?? null);

                        // 1. Grava o histórico da tramitação
                        \App\Models\ProcessoTramitacao::create([
                            'tenant_id' => $record->tenant_id,
                            'processo_digital_id' => $record->id,
                            'etapa_origem_id' => $record->etapa_atual_id,
                            'etapa_destino_id' => $etapaDestinoId, // Usa a variável segura
                            'usuario_id' => Filament::auth()->id(),
                            'parecer' => $data['parecer'],
                            'status_parecer' => $data['status_parecer'],
                        ]);

                        // 2. Atualiza a "Capa" do Processo
                        $novoStatus = 'em_andamento';
                        if ($data['status_parecer'] === 'reprovado') $novoStatus = 'pendente_correcao';
                        if ($data['status_parecer'] === 'concluido') $novoStatus = 'concluido';

                        $record->update([
                            'etapa_atual_id' => $etapaDestinoId, // Usa a variável segura
                            'status' => $novoStatus,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title($novoStatus === 'concluido' ? 'Processo Finalizado com Sucesso!' : 'Processo Tramitado com Sucesso!')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessoDigitals::route('/'),
            'view' => Pages\ViewProcessoDigital::route('/{record}')
        ];
    }
}