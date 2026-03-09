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
                            ->label(fn (Forms\Get $get) => $get('type') === 'juridica' ? 'Razão Social' : 'Nome Completo')
                            ->required()
                            ->maxLength(150),

                        Forms\Components\TextInput::make('trade_name')
                            ->label('Nome Fantasia')
                            ->maxLength(150)
                            ->visible(fn (Forms\Get $get) => $get('type') === 'juridica'),

                        Forms\Components\TextInput::make('cpf')
                            ->label('CPF')
                            ->mask('999.999.999-99')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'fisica')
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('cnpj')
                            ->label('CNPJ')
                            ->mask('99.999.999/9999-99')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'juridica')
                            ->unique(ignoreRecord: true),

                        Forms\Components\DatePicker::make('birth_date')
                            ->label(fn (Forms\Get $get) => $get('type') === 'juridica' ? 'Data de Fundação' : 'Data de Nascimento')
                            ->displayFormat('d/m/Y'),
                    ])->columns(2),
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
                    ->color(fn (string $state): string => match ($state) {
                        'fisica' => 'info',
                        'juridica' => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                Tables\Columns\TextColumn::make('cpf')
                    ->label('Documento')
                    ->getStateUsing(function (Pessoa $record) {
                        return $record->type === 'fisica' ? $record->cpf : $record->cnpj;
                    })
                    ->searchable(['cpf', 'cnpj']),
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