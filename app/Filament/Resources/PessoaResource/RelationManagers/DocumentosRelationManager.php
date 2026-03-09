<?php

namespace App\Filament\Resources\PessoaResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentos';
    protected static ?string $title = 'Anexos e Documentos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome do Documento (Ex: CNH, Contrato Social)')
                    ->required()
                    ->maxLength(255),

                Forms\Components\FileUpload::make('path')
                    ->label('Arquivo')
                    ->directory('documentos')
                    ->preserveFilenames()
                    ->maxSize(10240) // 10MB
                    ->openable() // 🛑 NOVO: Permite abrir o arquivo (PDF/Imagem) numa nova aba
                    ->downloadable() // 🛑 NOVO: Permite baixar direto pelo formulário
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Documento')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Anexado em')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Anexar Documento'),
            ])
            ->actions([
                // Ação para baixar o arquivo diretamente da tabela
                Tables\Actions\Action::make('download')
                    ->label('Baixar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => asset('storage/' . $record->path))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}