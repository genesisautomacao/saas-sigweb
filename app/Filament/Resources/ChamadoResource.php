<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChamadoResource\Pages;
use App\Models\Chamado;
use App\Models\FaseChamado;
use App\Services\Expo\ExpoPushService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChamadoResource extends Resource
{
    protected static ?string $model = Chamado::class;

    protected static ?string $tenantRelationshipName = 'chamados';

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'App de Chamados';

    protected static ?string $modelLabel = 'Chamado';

    protected static ?string $pluralModelLabel = 'Chamados';

    protected static ?int $navigationSort = 29;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Solicitação')->schema([
                Forms\Components\Select::make('categoria_chamado_id')->label('Categoria')
                    ->relationship('categoria', 'nome')->searchable()->preload(),
                Forms\Components\Select::make('fluxo_chamado_id')->label('Fluxo de Trabalho')
                    ->relationship('fluxo', 'nome')->searchable()->preload()->live(),
                Forms\Components\Select::make('fase_atual_id')->label('Fase Atual')
                    ->options(fn (Forms\Get $get) => $get('fluxo_chamado_id')
                        ? FaseChamado::where('fluxo_chamado_id', $get('fluxo_chamado_id'))->orderBy('ordem')->pluck('nome', 'id')
                        : []),
                Forms\Components\TextInput::make('status')->label('Status')->default('aberto'),
                Forms\Components\Textarea::make('descricao')->label('Descrição do problema')->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Solicitante')->schema([
                Forms\Components\TextInput::make('solicitante_nome')->label('Nome'),
                Forms\Components\TextInput::make('solicitante_telefone')->label('Telefone'),
                Forms\Components\TextInput::make('solicitante_email')->label('E-mail')->email(),
            ])->columns(3),

            Forms\Components\Section::make('Localização (item 181)')->schema([
                Forms\Components\TextInput::make('latitude')->label('Latitude (Y)')->numeric(),
                Forms\Components\TextInput::make('longitude')->label('Longitude (X)')->numeric(),
            ])->columns(2)->description('Preencha lat/lon ou marque no mapa. (lat/lon convertem para o ponto geográfico ao salvar.)'),

            Forms\Components\Section::make('Anexos')->schema([
                Forms\Components\FileUpload::make('fotos')->label('Fotos da solicitação (item 173)')
                    ->image()->multiple()->directory('chamados/fotos')->maxSize(4096),
                Forms\Components\Textarea::make('observacoes')->label('Observações'),
                Forms\Components\Textarea::make('anotacoes')->label('Anotações internas'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('protocolo')->label('Protocolo')->searchable()->copyable()->weight('bold'), // 160
                Tables\Columns\TextColumn::make('categoria.nome')->label('Categoria')->badge()->placeholder('—'),
                Tables\Columns\TextColumn::make('fluxo.nome')->label('Fluxo')->placeholder('—'),
                Tables\Columns\TextColumn::make('faseAtual.nome')->label('Fase Atual')->badge()->color('info')->placeholder('—'),
                Tables\Columns\TextColumn::make('solicitante_nome')->label('Solicitante')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('Atualizado em')->dateTime('d/m/Y H:i')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('categoria_chamado_id')->label('Categoria') // 161
                    ->relationship('categoria', 'nome')->searchable()->preload(),
                Filter::make('busca') // 160 — código/observações/anotações
                    ->form([Forms\Components\TextInput::make('termo')->label('Buscar (protocolo, observações, anotações)')])
                    ->query(fn (Builder $q, array $data) => $data['termo']
                        ? $q->where(fn ($w) => $w->where('protocolo', 'ilike', "%{$data['termo']}%")
                            ->orWhere('observacoes', 'ilike', "%{$data['termo']}%")
                            ->orWhere('anotacoes', 'ilike', "%{$data['termo']}%")
                            ->orWhere('descricao', 'ilike', "%{$data['termo']}%"))
                        : $q),
                Filter::make('criado_em') // 160 — data de criação
                    ->form([
                        Forms\Components\DatePicker::make('de')->label('Criado de'),
                        Forms\Components\DatePicker::make('ate')->label('Criado até'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['de'] ?? null, fn ($w, $v) => $w->whereDate('created_at', '>=', $v))
                        ->when($data['ate'] ?? null, fn ($w, $v) => $w->whereDate('created_at', '<=', $v))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(), // 164
                Tables\Actions\ActionGroup::make([
                    self::alterarCategoriaAction(),
                    self::alterarFaseAction(),
                    self::mensagemAction(),
                    self::verNoMapaAction(),
                    self::imprimirAction(),
                ])->label('Ações')->icon('heroicon-m-ellipsis-vertical')->button()->color('gray'),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    /** 165 → 166: altera a categoria e notifica o cidadão. */
    protected static function alterarCategoriaAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('alterar_categoria')
            ->label('Alterar Categoria')->icon('heroicon-o-tag')->color('warning')
            ->form([
                Forms\Components\Select::make('categoria_chamado_id')->label('Nova categoria')
                    ->options(fn () => \App\Models\CategoriaChamado::orderBy('nome')->pluck('nome', 'id'))
                    ->required(),
            ])
            ->action(function (Chamado $record, array $data) {
                $record->update(['categoria_chamado_id' => $data['categoria_chamado_id']]);
                $cat = \App\Models\CategoriaChamado::find($data['categoria_chamado_id']);
                self::notificarCidadao($record, 'Categoria atualizada', "Seu chamado {$record->protocolo} teve a categoria alterada para: {$cat?->nome}."); // 166
                Notification::make()->title('Categoria alterada e cidadão notificado.')->success()->send();
            });
    }

    /** 167 → 168: altera a fase atual, grava histórico e notifica o cidadão. */
    protected static function alterarFaseAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('alterar_fase')
            ->label('Alterar Fase')->icon('heroicon-o-arrow-path')->color('primary')
            ->form([
                Forms\Components\Select::make('fase_atual_id')->label('Nova fase')
                    ->options(fn (Chamado $record) => $record->fluxo_chamado_id
                        ? FaseChamado::where('fluxo_chamado_id', $record->fluxo_chamado_id)->orderBy('ordem')->pluck('nome', 'id')
                        : [])
                    ->required(),
            ])
            ->action(function (Chamado $record, array $data) {
                $record->update(['fase_atual_id' => $data['fase_atual_id']]);
                \App\Models\HistoricoFaseChamado::create([ // 168/174
                    'tenant_id' => $record->tenant_id,
                    'chamado_id' => $record->id,
                    'fase_id' => $data['fase_atual_id'],
                    'user_id' => Filament::auth()->id(),
                ]);
                $fase = FaseChamado::find($data['fase_atual_id']);
                self::notificarCidadao($record, 'Andamento do chamado', "Seu chamado {$record->protocolo} avançou para a fase: {$fase?->nome}."); // 168
                Notification::make()->title('Fase alterada e cidadão notificado.')->success()->send();
            });
    }

    /** 169/170/171: envia mensagem pública (com push ao cidadão) ou privada (interna). */
    protected static function mensagemAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('mensagem')
            ->label('Enviar Mensagem')->icon('heroicon-o-chat-bubble-left-right')->color('info')
            ->form([
                Forms\Components\Textarea::make('texto')->label('Mensagem')->required()->maxLength(2000),
                Forms\Components\Toggle::make('publica')->label('Pública (o cidadão recebe notificação no app)')->default(true)
                    ->helperText('Desligado = mensagem interna da prefeitura (o cidadão NÃO vê — item 170).'),
            ])
            ->action(function (Chamado $record, array $data) {
                // push automático via MensagemChamado::booted() quando pública (169/171)
                \App\Models\MensagemChamado::create([
                    'tenant_id' => $record->tenant_id,
                    'chamado_id' => $record->id,
                    'user_id' => Filament::auth()->id(),
                    'texto' => $data['texto'],
                    'publica' => (bool) $data['publica'],
                ]);
                Notification::make()->title($data['publica'] ? 'Mensagem pública enviada (cidadão notificado).' : 'Mensagem interna registrada.')->success()->send();
            });
    }

    protected static function notificarCidadao(Chamado $chamado, string $title, string $body): void
    {
        if (! $chamado->user_id) {
            return;
        }
        $cidadao = \App\Models\User::query()->select('id', 'expo_push_token')->find($chamado->user_id);
        if (! $cidadao?->expo_push_token) {
            return;
        }
        app(ExpoPushService::class)->send($cidadao->expo_push_token, $title, $body, ['tipo' => 'chamado', 'chamadoId' => $chamado->id]);
    }

    /** 162 — tabela → mapa: posiciona/identifica o chamado no mapa. */
    protected static function verNoMapaAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('ver_no_mapa')
            ->label('Ver no Mapa')->icon('heroicon-o-map-pin')->color('success')
            ->visible(fn (Chamado $record) => $record->geo_json !== null)
            ->url(function (Chamado $record) {
                $geo = $record->geo_json;
                if (! $geo || ! isset($geo->coordinates[0], $geo->coordinates[1])) {
                    return null;
                }
                $tenant = Filament::getTenant();

                return url('/app/'.$tenant->slug.'/mapa-interativo?layer=chamados&focus_lat='.$geo->coordinates[1].'&focus_lon='.$geo->coordinates[0].'&zoom=18');
            })
            ->openUrlInNewTab();
    }

    /** 174 — impressão da solicitação (mapa, mensagens, boletim, histórico de fases). */
    protected static function imprimirAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('imprimir')
            ->label('Imprimir PDF')->icon('heroicon-o-printer')->color('gray')
            ->action(fn (Chamado $record, \App\Services\Chamados\ChamadoPdfService $pdf) => $pdf->generate($record));
    }

    public static function getRelations(): array
    {
        return [
            ChamadoResource\RelationManagers\MensagensRelationManager::class,
            ChamadoResource\RelationManagers\HistoricoFasesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChamados::route('/'),
            'create' => Pages\CreateChamado::route('/create'),
            'view' => Pages\ViewChamado::route('/{record}'),
            'edit' => Pages\EditChamado::route('/{record}/edit'),
        ];
    }
}
