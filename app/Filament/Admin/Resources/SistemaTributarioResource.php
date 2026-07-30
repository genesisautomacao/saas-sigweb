<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SistemaTributarioResource\Pages;
use App\Models\SistemaTributario;
use App\Models\Tenant;
use App\Services\Fiscal\MapaFiscalService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * R67-5 — catálogo GLOBAL de sistemas tributários (Betha, GOVBR, IPM, Fiorilli…).
 * O de/para de campos é parametrizado UMA vez por sistema, aqui no /admin; cada
 * prefeitura aponta para uma entrada no cadastro dela (TenantResource → seção
 * "Integração Tributária"). Export diferente do mesmo fornecedor (versão antiga,
 * layout customizado) = outra entrada no catálogo (ex.: "Betha — layout 2").
 */
class SistemaTributarioResource extends Resource
{
    protected static ?string $model = SistemaTributario::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Configurações Globais';

    protected static ?string $modelLabel = 'Sistema Tributário';

    protected static ?string $pluralModelLabel = 'Sistemas Tributários';

    protected static ?string $slug = 'sistemas-tributarios';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')
                ->schema([
                    Forms\Components\TextInput::make('nome')
                        ->label('Nome do sistema')
                        ->placeholder('Ex.: Betha, GOVBR, IPM, Fiorilli, Betha — layout 2…')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true),

                    Forms\Components\Toggle::make('ativo')
                        ->label('Ativo')
                        ->default(true)
                        ->inline(false),

                    // PONTO DE LIGAÇÃO: qual campo da unidade localiza o imóvel neste
                    // fornecedor (uns sistemas localizam pelo código do cadastro,
                    // outros pela inscrição imobiliária). Usado na busca ☁️/Sincronizar,
                    // no JSON de simulação e passado ao conector de API.
                    Forms\Components\Select::make('chave_ligacao')
                        ->label('Chave de ligação (localizador do imóvel)')
                        ->options(SistemaTributario::CHAVES_LIGACAO)
                        ->default('codigo_imovel_tributario')
                        ->required()
                        ->helperText('Qual campo da unidade imobiliária identifica o imóvel no sistema do fornecedor.'),

                    // Conector de API real (implementado em código — registry
                    // IntegraPrefeituraService::DRIVERS). Sem conector, o sistema
                    // funciona só com arquivos (simulação/importação) + de/para.
                    Forms\Components\Select::make('driver')
                        ->label('Conector de API real')
                        ->placeholder('Nenhum (arquivos + de/para)')
                        ->options(fn () => array_combine(
                            array_keys(\App\Services\ApiTools\IntegraPrefeituraService::DRIVERS),
                            array_keys(\App\Services\ApiTools\IntegraPrefeituraService::DRIVERS),
                        ))
                        ->nullable()
                        ->helperText('Aparece aqui quando o conector do fornecedor for implementado no sistema. Com conector, as prefeituras deste sistema podem ligar o modo "Produção".'),

                    Forms\Components\Textarea::make('observacao')
                        ->label('Observações (interno)')
                        ->placeholder('Ex.: layout do export usado desde 2024; prefeituras X e Y…')
                        ->rows(2)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Correspondência de campos (de/para)')
                ->description('À esquerda, o nome do campo como ele chega no export/API deste sistema; à direita, o campo correspondente no SIGWEB. O dado original nunca é perdido — o JSON bruto continua guardado por unidade.')
                ->schema([
                    Forms\Components\KeyValue::make('mapa')
                        ->hiddenLabel()
                        ->keyLabel('Campo no sistema tributário')
                        ->valueLabel('Campo no SIGWEB')
                        ->addActionLabel('Adicionar correspondência')
                        ->helperText('Campos do SIGWEB: '.implode(', ', array_keys(MapaFiscalService::camposCanonicos())))
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Campos extras a exibir')
                ->description('Campos que só existem neste sistema e devem aparecer no BIC e nas telas do imóvel das prefeituras que o usam.')
                ->schema([
                    Forms\Components\Repeater::make('extras')
                        ->hiddenLabel()
                        ->schema([
                            Forms\Components\TextInput::make('origem')->label('Campo no sistema tributário')->required(),
                            Forms\Components\TextInput::make('label')->label('Como exibir')->required(),
                        ])
                        ->columns(2)
                        ->addActionLabel('Adicionar campo extra')
                        ->default([])
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')
                    ->label('Sistema')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('driver')
                    ->label('API real')
                    ->state(fn (SistemaTributario $record) => $record->driver
                        ? (array_key_exists($record->driver, \App\Services\ApiTools\IntegraPrefeituraService::DRIVERS) ? $record->driver : "{$record->driver} (?)")
                        : 'arquivos')
                    ->badge()
                    ->color(fn (SistemaTributario $record) => $record->driver ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('mapa')
                    ->label('Correspondências')
                    ->state(fn (SistemaTributario $record) => count($record->mapa ?? []).' campo(s)')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('extras')
                    ->label('Extras no BIC')
                    ->state(fn (SistemaTributario $record) => count($record->extras ?? []).' campo(s)')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('prefeituras')
                    ->label('Prefeituras usando')
                    ->state(fn (SistemaTributario $record) => Tenant::where('data->sistema_tributario_id', $record->id)->count())
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                Tables\Columns\ToggleColumn::make('ativo')->label('Ativo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    // Não deixa excluir sistema em uso — as prefeituras perderiam o de/para
                    ->before(function (Tables\Actions\DeleteAction $action, SistemaTributario $record) {
                        $emUso = Tenant::where('data->sistema_tributario_id', $record->id)->count();
                        if ($emUso > 0) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Sistema em uso')
                                ->body("{$emUso} prefeitura(s) apontam para este sistema. Troque-as antes de excluir.")
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->emptyStateHeading('Nenhum sistema tributário cadastrado')
            ->emptyStateDescription('Cadastre o de/para de cada sistema (Betha, GOVBR, IPM…) uma única vez e aponte as prefeituras para ele.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSistemaTributarios::route('/'),
            'create' => Pages\CreateSistemaTributario::route('/create'),
            'edit' => Pages\EditSistemaTributario::route('/{record}/edit'),
        ];
    }
}
