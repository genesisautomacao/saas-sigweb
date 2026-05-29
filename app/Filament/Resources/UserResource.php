<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    // Avisa ao Filament que a relação entre User e Tenant é Muitos-Para-Muitos (plural)
    protected static ?string $tenantOwnershipRelationshipName = 'tenants';

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $modelLabel = 'Usuário da Equipe';
    protected static ?string $pluralModelLabel = 'Equipe';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 33;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome Completo')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('E-mail (Login)')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Forms\Components\TextInput::make('password')
                    ->label('Senha')
                    ->password()
                    ->dehydrateStateUsing(fn($state) => Hash::make($state))
                    ->dehydrated(fn($state) => filled($state))
                    ->required(fn(string $context): bool => $context === 'create')
                    ->maxLength(255),

                Forms\Components\Select::make('roles')
                    ->label('Papéis de Acesso')
                    ->multiple()
                    ->searchable()
                    ->options(function () {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return \App\Models\Role::where('tenant_id', $tenant->id)
                            ->pluck('name', 'name');
                    })
                    ->afterStateHydrated(function (Forms\Components\Select $component, ?\App\Models\User $record) {
                        if ($record) {
                            $component->state($record->roles->pluck('name')->toArray());
                        }
                    })
                    ->saveRelationshipsUsing(function (\App\Models\User $record, $state) {
                        $tenant = \Filament\Facades\Filament::getTenant();

                        // FORÇA o Spatie a olhar para a Tenant correta neste exato milissegundo
                        setPermissionsTeamId($tenant->id);

                        // Agora sim, o Spatie vai encontrar o papel "Vendedor"
                        $record->syncRoles($state);
                    })
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ITEM 5: Adicionado ->sortable() e ->searchable() em tudo
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),

                // ITEM 2: Coluna de Papel com visual de "Etiqueta" (Badge)
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Papel')
                    ->badge() // Transforma o texto em uma etiqueta colorida
                    ->color('info') // Cor azulzinha
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Esconde por padrão para não poluir, mas o usuário pode mostrar
            ])
            ->filters([
                // ITEM 3: Filtro por Papel de Acesso
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Filtrar por Papel')
                    ->preload()
                    ->multiple(), // Permite selecionar mais de um papel no filtro
            ])
            // ITEM 4: Ações em um menu de 3 pontinhos (ActionGroup)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\DeleteAction::make()
                        // 1. Esconde o botão excluir individual se o ID da linha for o mesmo do usuário logado
                        ->hidden(fn(\App\Models\User $record) => $record->id === \Filament\Facades\Filament::auth()->id()),

                ])->tooltip('Ações'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir Selecionados'),
                ]),
            ])
            // 2. Trava a caixa de seleção em lote: Retorna "false" (não selecionável) se for o próprio usuário
            ->checkIfRecordIsSelectableUsing(
                fn(\App\Models\User $record): bool => $record->id !== \Filament\Facades\Filament::auth()->id(),
            );
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
