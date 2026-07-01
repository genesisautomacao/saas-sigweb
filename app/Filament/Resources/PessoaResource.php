<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PessoaResource\Pages;
use App\Models\Pessoa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Traits\HasTenantModule;

class PessoaResource extends Resource
{
    // Aplica a validação de Módulos do Tenant
    use HasTenantModule;

    // O nome do módulo que deve estar ativo no Tenant para libertar este ecrã
    protected static ?string $tenantModule = 'administrativo';

    protected static ?string $model = Pessoa::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    // Agrupamento na barra lateral
    protected static ?string $navigationGroup = 'Módulo Administrativo';

    protected static ?string $modelLabel = 'Pessoa / Empresa';
    protected static ?string $pluralModelLabel = 'Pessoas e Empresas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados Principais')
                    ->schema([
                        Forms\Components\Radio::make('type')
                            ->label('Tipo de Registo')
                            ->options([
                                'fisica' => 'Pessoa Física',
                                'juridica' => 'Pessoa Jurídica',
                            ])
                            ->default('fisica')
                            ->inline()
                            ->live(), // Reativo para mostrar/esconder campos

                        Forms\Components\TextInput::make('name')
                            ->label(fn(Forms\Get $get) => $get('type') === 'juridica' ? 'Razão Social' : 'Nome Completo')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('trade_name')
                            ->label('Nome Fantasia')
                            ->maxLength(150)
                            ->visible(fn(Forms\Get $get) => $get('type') === 'juridica'),

                        Forms\Components\TextInput::make('cpf')
                            ->label('CPF')
                            ->mask('999.999.999-99')
                            ->visible(fn(Forms\Get $get) => $get('type') === 'fisica')
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('cnpj')
                            ->label('CNPJ')
                            ->mask('99.999.999/9999-99')
                            ->visible(fn(Forms\Get $get) => $get('type') === 'juridica')
                            ->unique(ignoreRecord: true),

                        // Adicione logo após o campo do CPF:
                        Forms\Components\TextInput::make('cns')
                            ->label('Cartão Nacional de Saúde (CNS)')
                            ->maxLength(15)
                            ->numeric() // CNS só tem números
                            ->visible(fn(Forms\Get $get) => $get('type') === 'fisica') // Só mostra se for pessoa física!
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('birth_date')
                            ->label(fn(Forms\Get $get) => $get('type') === 'juridica' ? 'Data de Fundação' : 'Data de Nascimento')
                            ->displayFormat('d/m/Y'),

                        // --- SEÇÃO DE SAÚDE (PREENCHIDA PELO ETL DO E-SUS) ---
                        Forms\Components\Section::make('Condições de Saúde (e-SUS AB)')
                            ->icon('heroicon-o-heart')
                            ->description('Estes dados são sincronizados automaticamente com o Ministério da Saúde toda madrugada. Não devem ser alterados manualmente.')
                            ->schema([
                                // Usamos um Group com relationship para carregar os dados da tabela auxiliar
                                Forms\Components\Group::make()
                                    ->relationship('condicoesSaude') // Nome do método criado no Model Pessoa
                                    ->schema([
                                        Forms\Components\Toggle::make('is_hipertenso')->label('Hipertenso')->disabled(),
                                        Forms\Components\Toggle::make('is_diabetico')->label('Diabético')->disabled(),
                                        Forms\Components\Toggle::make('is_gestante')->label('Gestante')->disabled(),
                                        Forms\Components\Toggle::make('is_fumante')->label('Fumante')->disabled(),
                                        Forms\Components\Toggle::make('is_pcd')->label('PcD (Deficiência)')->disabled(),
                                        Forms\Components\Toggle::make('is_acamado')->label('Acamado')->disabled(),

                                        Forms\Components\Placeholder::make('ultima_sincronizacao')
                                            ->label('Última Sincronização via e-SUS')
                                            ->content(fn($record) => $record?->ultima_sincronizacao ? \Carbon\Carbon::parse($record->ultima_sincronizacao)->format('d/m/Y H:i:s') : 'Nunca sincronizado')
                                            ->columnSpanFull(),
                                    ])->columns(3),
                            ])
                            ->visible(fn(Forms\Get $get) => $get('type') === 'fisica') // Só exibe para Pessoas Físicas
                            ->collapsed(), // Mantém fechado por padrão para não poluir a tela

                    ])->columns(2),

                // Pessoa - Social (item 092) — só para pessoa física
                Forms\Components\Section::make('Dados Sociais (Assistência Social)')
                    ->icon('heroicon-o-identification')
                    ->description('Documentos e vínculos usados no Módulo de Cadastro Social.')
                    ->collapsed()
                    ->visible(fn(Forms\Get $get) => $get('type') === 'fisica')
                    ->schema([
                        Forms\Components\TextInput::make('rg')->label('RG')->maxLength(255),
                        Forms\Components\TextInput::make('ctps')->label('CTPS')->maxLength(255),
                        Forms\Components\TextInput::make('pis')->label('PIS/PASEP')->maxLength(255),
                        Forms\Components\TextInput::make('nis')->label('NIS')->maxLength(255),
                        Forms\Components\TextInput::make('certidao_nascimento')->label('Certidão de Nascimento')->maxLength(255),
                        Forms\Components\TextInput::make('telefone')->label('Telefone')->maxLength(255),
                        Forms\Components\Select::make('estado_civil')->label('Estado Civil')
                            ->options([
                                'solteiro' => 'Solteiro(a)', 'casado' => 'Casado(a)', 'divorciado' => 'Divorciado(a)',
                                'viuvo' => 'Viúvo(a)', 'uniao_estavel' => 'União Estável', 'separado' => 'Separado(a)',
                            ]),
                        Forms\Components\Select::make('sexo')->label('Sexo')
                            ->options(['masculino' => 'Masculino', 'feminino' => 'Feminino', 'outro' => 'Outro']),
                        Forms\Components\Select::make('pai_id')->label('Pai')
                            ->relationship('pai', 'name', fn($query) => $query->where('type', 'fisica'))
                            ->searchable()->preload(),
                        Forms\Components\Select::make('mae_id')->label('Mãe')
                            ->relationship('mae', 'name', fn($query) => $query->where('type', 'fisica'))
                            ->searchable()->preload(),
                        Forms\Components\Select::make('conjuge_id')->label('Cônjuge')
                            ->relationship('conjuge', 'name', fn($query) => $query->where('type', 'fisica'))
                            ->searchable()->preload(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome / Razão Social')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'fisica' => 'info',
                        'juridica' => 'warning',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('cpf')
                    ->label('Documento')
                    ->getStateUsing(function (Pessoa $record) {
                        return $record->type === 'fisica' ? $record->cpf : $record->cnpj;
                    })
                    ->searchable(['cpf', 'cnpj']),

                // Adicione logo após a coluna 'cpf':
                Tables\Columns\TextColumn::make('cns')
                    ->label('Cartão SUS (CNS)')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // Escondido por padrão para não poluir a tabela
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'fisica' => 'Física',
                        'juridica' => 'Jurídica',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PessoaResource\RelationManagers\ContatosRelationManager::class,
            PessoaResource\RelationManagers\EnderecosRelationManager::class,
            PessoaResource\RelationManagers\DocumentosRelationManager::class,
            PessoaResource\RelationManagers\RendasRelationManager::class,
            PessoaResource\RelationManagers\DeficienciasRelationManager::class,
            PessoaResource\RelationManagers\OcorrenciasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPessoas::route('/'),
            'create' => Pages\CreatePessoa::route('/create'),
            'edit' => Pages\EditPessoa::route('/{record}/edit'),
        ];
    }
}
