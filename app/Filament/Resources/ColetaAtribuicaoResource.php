<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ColetaAtribuicaoResource\Pages;
use App\Models\ColetaAtribuicao;
use App\Models\Quadra;
use App\Models\User;
use App\Traits\HasTenantModule;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * R67-4 — região de trabalho por cadastrador (quadras/bairros + período).
 * O app baixa SÓ os lotes das quadras atribuídas ao usuário no período vigente.
 */
class ColetaAtribuicaoResource extends Resource
{
    use HasTenantModule;

    protected static ?string $tenantModule = 'coleta_cadastral'; // D4 (docs/Modulos_Permissoes.txt)

    protected static ?string $model = ColetaAtribuicao::class;

    // Multi-tenant: nome da relação no model Tenant (o plural automático "coletaAtribuicaos" não existe)
    protected static ?string $tenantRelationshipName = 'coletaAtribuicoes';

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Coleta cadastral';

    protected static ?string $modelLabel = 'Atribuição de Região';

    protected static ?string $pluralModelLabel = 'Atribuições de Região';

    protected static ?string $slug = 'atribuicoes-coleta';

    protected static ?int $navigationSort = 34;

    protected static ?string $navigationLabel = 'Regiões dos Cadastradores';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Cadastrador e período')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Cadastrador')
                        ->options(fn () => User::query()
                            ->where('tipo', '!=', 'cidadao')
                            ->whereHas('tenants', fn ($q) => $q->whereKey(Filament::getTenant()?->id))
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->helperText('Usuários com papel Master/Gerente ignoram a restrição e baixam toda a base.'),

                    Forms\Components\Toggle::make('ativo')->label('Ativa')->default(true),

                    Forms\Components\DatePicker::make('data_inicio')
                        ->label('Início')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    Forms\Components\DatePicker::make('data_fim')
                        ->label('Fim (opcional)')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->afterOrEqual('data_inicio')
                        ->helperText('Em branco = sem prazo definido.'),
                ])->columns(2),

            Forms\Components\Section::make('Região de trabalho')
                ->description('Clique nas quadras do mapa para montar a região deste cadastrador.')
                ->schema([
                    Forms\Components\ViewField::make('quadra_ids')
                        ->hiddenLabel()
                        ->view('filament.forms.components.mapa-selecao-quadras')
                        ->default([])
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if (empty($value)) {
                                $fail('Selecione ao menos uma quadra no mapa.');
                            }
                        })
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('observacao')->label('Observação')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('data_inicio', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Cadastrador')->weight('bold')->searchable(),

                Tables\Columns\TextColumn::make('periodo')
                    ->label('Período')
                    ->state(fn (ColetaAtribuicao $r) => $r->data_inicio?->format('d/m/Y')
                        .' → '.($r->data_fim?->format('d/m/Y') ?? 'sem prazo')),

                Tables\Columns\TextColumn::make('regiao')
                    ->label('Região')
                    ->state(function (ColetaAtribuicao $r) {
                        $ids = $r->quadra_ids ?? [];
                        $nomes = Quadra::withoutGlobalScopes()->whereIn('id', $ids)->orderBy('name')->pluck('name');

                        return count($ids).' quadra(s)'.($nomes->isNotEmpty() ? ': '.$nomes->take(4)->implode(', ').($nomes->count() > 4 ? '…' : '') : '');
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('vigencia')
                    ->label('Situação')
                    ->badge()
                    ->state(function (ColetaAtribuicao $r) {
                        if (! $r->ativo) {
                            return 'Inativa';
                        }
                        $hoje = now()->startOfDay();
                        if ($r->data_inicio && $r->data_inicio->gt($hoje)) {
                            return 'Futura';
                        }
                        if ($r->data_fim && $r->data_fim->lt($hoje)) {
                            return 'Encerrada';
                        }

                        return 'Vigente';
                    })
                    ->color(fn ($state) => match ($state) {
                        'Vigente' => 'success',
                        'Futura' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')->label('Criada em')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Cadastrador')
                    ->options(fn () => User::query()
                        ->where('tipo', '!=', 'cidadao')
                        ->whereHas('tenants', fn ($q) => $q->whereKey(Filament::getTenant()?->id))
                        ->orderBy('name')
                        ->pluck('name', 'id')),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Nenhuma região atribuída')
            ->emptyStateDescription('Sem atribuição vigente, o cadastrador não baixa lotes no app (exceto Master/Gerente).');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListColetaAtribuicoes::route('/'),
            'create' => Pages\CreateColetaAtribuicao::route('/create'),
            'edit' => Pages\EditColetaAtribuicao::route('/{record}/edit'),
        ];
    }
}
