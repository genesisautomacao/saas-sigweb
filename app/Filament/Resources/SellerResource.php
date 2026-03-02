<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SellerResource\Pages;
use App\Filament\Resources\SellerResource\RelationManagers;
use App\Models\Seller;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SellerResource extends Resource
{
    use \App\Traits\HasTenantModule;

    // 2. Define qual é o módulo que esta tela exige
    protected static ?string $tenantModule = 'leads';

    protected static ?string $model = Seller::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $modelLabel = 'Vendedor';
    protected static ?string $pluralModelLabel = 'Vendedores';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados de Acesso do Vendedor')
                    ->description('Preencha os dados abaixo para criar o acesso deste vendedor ao sistema.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome Completo')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail (Login)')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            // Exige senha apenas na hora de criar um vendedor novo
                            ->required(fn(string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->dehydrated(fn($state) => filled($state)),

                        Forms\Components\Select::make('role_name')
                            ->label('Papel de Acesso')
                            ->options(function () {
                                $tenant = \Filament\Facades\Filament::getTenant();
                                // Puxa apenas os papéis da transportadora atual (Manager, Vendedor, etc)
                                return \Spatie\Permission\Models\Role::where('tenant_id', $tenant?->id)
                                    ->pluck('name', 'name');
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Forms\Components\Section::make('Dados Comerciais')
                    ->schema([
                        Forms\Components\TextInput::make('region')
                            ->label('Região de Atuação')
                            ->placeholder('Ex: Zona ABC')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('commission_rate')
                            ->label('Taxa de Comissão (%)')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Vendedor Ativo?')
                            ->default(true)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nome do Vendedor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('E-mail')
                    ->searchable(),

                Tables\Columns\TextColumn::make('region')
                    ->label('Região')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('commission_rate')
                    ->label('Comissão')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellers::route('/'),
            'create' => Pages\CreateSeller::route('/create'),
            'edit' => Pages\EditSeller::route('/{record}/edit'),
        ];
    }
}
