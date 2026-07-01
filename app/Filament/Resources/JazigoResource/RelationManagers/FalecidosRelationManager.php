<?php

namespace App\Filament\Resources\JazigoResource\RelationManagers;

use App\Models\JazigoFalecido;
use App\Models\Pessoa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FalecidosRelationManager extends RelationManager
{
    protected static string $relationship = 'falecidos';
    protected static ?string $title = 'Falecidos';
    protected static ?string $modelLabel = 'Falecido';
    protected static ?string $pluralModelLabel = 'Falecidos';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')->schema([
                Forms\Components\Select::make('pessoa_id')
                    ->label('Pessoa Cadastrada (Opcional)')
                    ->options(fn() => Pessoa::pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->live()
                    ->helperText('Selecione se o falecido já está no cadastro de pessoas. Caso contrário, preencha o nome abaixo.')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('nome_falecido')
                    ->label('Nome do Falecido')
                    ->maxLength(255)
                    ->nullable()
                    ->visible(fn(Get $get) => ! $get('pessoa_id'))
                    ->required(fn(Get $get) => ! $get('pessoa_id'))
                    ->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Documentos Anexados')
                ->schema([
                    Forms\Components\Repeater::make('documentos')
                        ->relationship('documentos')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nome do Documento')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\FileUpload::make('path')
                                ->label('Arquivo')
                                ->directory('documentos/falecidos')
                                ->preserveFilenames()
                                ->maxSize(10240)
                                ->openable()
                                ->downloadable()
                                ->required()
                                ->columnSpanFull(),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('Anexar Documento')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),

            Forms\Components\Section::make('Dados do Óbito')->schema([
                Forms\Components\DatePicker::make('data_obito')
                    ->label('Data do Óbito')
                    ->nullable()
                    ->displayFormat('d/m/Y'),

                Forms\Components\DatePicker::make('data_sepultamento')
                    ->label('Data do Sepultamento')
                    ->nullable()
                    ->displayFormat('d/m/Y'),

                Forms\Components\TextInput::make('numero_certidao_obito')
                    ->label('Nº da Certidão de Óbito')
                    ->maxLength(255)
                    ->nullable()
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('observacao')
                    ->label('Observação')
                    ->rows(3)
                    ->nullable()
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome_falecido')
            ->columns([
                Tables\Columns\TextColumn::make('nome_display')
                    ->label('Nome')
                    ->getStateUsing(fn(JazigoFalecido $record) => $record->nome_display)
                    ->searchable(query: function ($query, string $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('nome_falecido', 'ilike', "%{$search}%")
                              ->orWhereHas('pessoa', fn($p) => $p->where('name', 'ilike', "%{$search}%"));
                        });
                    })
                    ->weight('bold'),

                Tables\Columns\IconColumn::make('pessoa_id')
                    ->label('Cadastrado')
                    ->boolean()
                    ->trueIcon('heroicon-o-user-circle')
                    ->falseIcon('heroicon-o-user')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn(JazigoFalecido $record) => $record->pessoa_id ? 'Vinculado ao cadastro de pessoas' : 'Nome livre'),

                Tables\Columns\TextColumn::make('data_obito')
                    ->label('Óbito')
                    ->date('d/m/Y')
                    ->default('—'),

                Tables\Columns\TextColumn::make('data_sepultamento')
                    ->label('Sepultamento')
                    ->date('d/m/Y')
                    ->default('—'),

                Tables\Columns\TextColumn::make('numero_certidao_obito')
                    ->label('Certidão')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar Falecido')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = \Filament\Facades\Filament::getTenant()->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nenhum falecido registrado')
            ->emptyStateDescription('Clique em "Registrar Falecido" para vincular o primeiro ocupante a este jazigo.')
            ->emptyStateIcon('heroicon-o-heart');
    }
}
