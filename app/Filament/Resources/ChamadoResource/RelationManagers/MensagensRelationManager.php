<?php

namespace App\Filament\Resources\ChamadoResource\RelationManagers;

use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MensagensRelationManager extends RelationManager
{
    protected static string $relationship = 'mensagens';

    protected static ?string $title = 'Mensagens (públicas / privadas)';

    protected static ?string $recordTitleAttribute = 'texto';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('texto')->label('Mensagem')->required()->maxLength(2000)->columnSpanFull(),
            Forms\Components\Toggle::make('publica')
                ->label('Pública (o cidadão recebe notificação no app — item 169)')
                ->default(true)
                ->helperText('Desligado = mensagem interna da prefeitura, invisível ao cidadão (item 170).'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('remetente.name')->label('De')->placeholder('—'),
                Tables\Columns\TextColumn::make('texto')->label('Mensagem')->wrap()->limit(140),
                Tables\Columns\IconColumn::make('publica')->label('Pública')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Enviada')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Nova Mensagem')
                    ->mutateFormDataUsing(fn (array $data): array => array_merge($data, [
                        'tenant_id' => Filament::getTenant()->id,
                        'user_id' => Filament::auth()->id(),
                    ])),
            ])
            ->actions([]);
    }
}
