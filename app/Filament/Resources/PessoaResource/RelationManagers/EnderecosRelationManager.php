<?php

namespace App\Filament\Resources\PessoaResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;

class EnderecosRelationManager extends RelationManager
{
    protected static string $relationship = 'enderecos';
    protected static ?string $title = 'Endereços';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('cep')
                    ->label('CEP')
                    ->mask('99999-999')
                    ->required()
                    ->live(debounce: 500)
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (blank($state)) return;
                        
                        $cep = preg_replace('/[^0-9]/', '', $state);
                        if (strlen($cep) !== 8) return;

                        try {
                            $response = Http::timeout(4)
                                ->withHeaders(['User-Agent' => 'SaaS-Sigweb-App/1.0'])
                                ->withoutVerifying()
                                ->get("https://viacep.com.br/ws/{$cep}/json/");

                            if ($response->successful() && !isset($response['erro'])) {
                                $set('address', $response['logradouro'] ?? null);
                                $set('neighborhood', $response['bairro'] ?? null);
                                $set('city', $response['localidade'] ?? null);
                                $set('state', $response['uf'] ?? null);
                            } else {
                                \Filament\Notifications\Notification::make()->title('CEP não encontrado')->warning()->send();
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Serviço de CEP instável. Preencha manualmente.')->danger()->send();
                        }
                    })
                    ->columnSpanFull(),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('address')->label('Logradouro (Rua/Av)')->required()->columnSpan(2),
                    Forms\Components\TextInput::make('number')->label('Número')->required(),
                ]),

                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('complement')->label('Complemento'),
                    Forms\Components\TextInput::make('neighborhood')->label('Bairro')->required(),
                    Forms\Components\TextInput::make('city')->label('Cidade')->required(),
                ]),

                Forms\Components\TextInput::make('state')
                    ->label('UF')
                    ->maxLength(2)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('address')
            ->columns([
                Tables\Columns\TextColumn::make('address')
                    ->label('Endereço')
                    ->description(fn ($record) => $record->neighborhood . ' - ' . $record->city . '/' . $record->state)
                    ->searchable(),

                Tables\Columns\TextColumn::make('number')->label('Nº'),
                Tables\Columns\TextColumn::make('cep')->label('CEP')->searchable(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Novo Endereço')->modalWidth('2xl'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->modalWidth('2xl'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}