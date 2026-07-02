<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SetorResource\Pages;
use App\Models\Setor;
use App\Traits\HasTenantModule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Cadastro de Setor/Departamento do motor de Processos (swimlanes — item 206).
 * Permissão única `gerenciar_setores` (via SetorPolicy). Ver processosConceito.md §9.6.
 */
class SetorResource extends Resource
{
    use HasTenantModule;
    protected static ?string $tenantModule = 'processos';
    protected static ?string $tenantRelationshipName = 'setores';

    protected static ?string $model = Setor::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Processos Digitais';
    protected static ?string $modelLabel = 'Setor / Departamento';
    protected static ?string $pluralModelLabel = 'Setores / Departamentos';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')
                ->label('Nome do Setor (ex.: Secretaria de Obras)')
                ->required()
                ->maxLength(255),
            Forms\Components\ColorPicker::make('cor')
                ->label('Cor (swimlane)')
                ->default('#6366f1'),

            // Item 1 — usuários do setor (obrigatório): apenas usuários do tenant atual COM papel
            // atribuído (exclui cidadãos, que não têm papel). Definem quem acessa as etapas do setor.
            Forms\Components\Select::make('users')
                ->label('Usuários do Setor')
                ->relationship(
                    name: 'users',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query
                        ->whereHas('tenants', fn ($q) => $q->where('tenants.id', \Filament\Facades\Filament::getTenant()?->id))
                        ->whereHas('roles'), // só quem tem papel no tenant atual (não-cidadão)
                )
                ->multiple()
                ->preload()
                ->searchable()
                ->required()
                ->helperText('Apenas usuários com papel no sistema. Somente eles poderão ver e agir nas etapas deste setor.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')->label('ID')->sortable(),
                Tables\Columns\ColorColumn::make('cor')->label('Cor'),
                Tables\Columns\TextColumn::make('nome')->label('Setor')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('users_count')->counts('users')->label('Usuários')->badge()->color('info'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSetores::route('/')];
    }
}
