<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $modelLabel = 'Papel de Acesso';
    protected static ?string $pluralModelLabel = 'Papéis de Acesso';
    protected static ?string $navigationGroup = 'Configurações';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\TextInput::make('name')
                    ->label('Nome do Papel')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule) {
                            $tenant = \Filament\Facades\Filament::getTenant();
                            // Garante que a validação de nome único ocorra apenas DENTRO da Tenant atual
                            return $rule->where('tenant_id', $tenant?->id);
                        }
                    ),

                Forms\Components\Section::make('Permissões de Acesso')
                    ->description('Selecione as permissões organizadas por módulo do sistema.')
                    ->schema([

                        // CAIXA 1: LEADS E CRM
                        Forms\Components\Fieldset::make('Módulo: CRM (Leads e Configurações)')
                            ->visible(function () {
                                $tenant = \Filament\Facades\Filament::getTenant();
                                return $tenant && in_array('leads', $tenant->modules ?? []);
                            })
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_leads')
                                    ->label('Gestão de Leads')
                                    ->options([
                                        'view_leads' => 'Visualizar Leads',
                                        'create_leads' => 'Criar Leads',
                                        'edit_leads' => 'Editar Leads',
                                        'delete_leads' => 'Excluir Leads',
                                        'view_my_leads' => 'Visualizar APENAS meus Leads',
                                        'import_leads' => 'Importar Leads (Planilha)',
                                    ])
                                    ->bulkToggleable(),

                                Forms\Components\CheckboxList::make('permissions_settings')
                                    ->label('Configurações do CRM')
                                    ->options([
                                        'manage_crm_settings' => 'Gerenciar Status, Origens e Potencial',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 2: USUÁRIOS E VENDEDORES
                        Forms\Components\Fieldset::make('Módulo: Equipe (Usuários)')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_users')
                                    ->label('Gestão de Usuários')
                                    ->options([
                                        'view_users' => 'Visualizar Usuários',
                                        'create_users' => 'Criar Usuários',
                                        'edit_users' => 'Editar Usuários',
                                        'delete_users' => 'Excluir Usuários',
                                    ])
                                    ->bulkToggleable(),

                            ])->columns(1)->columnSpan(1),

                        // CAIXA 3: PAPÉIS
                        Forms\Components\Fieldset::make('Módulo: Segurança (Papéis de Acesso)')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_roles')
                                    ->label('Gestão de Papéis')
                                    ->options([
                                        'view_roles' => 'Visualizar Papéis',
                                        'create_roles' => 'Criar Papéis',
                                        'edit_roles' => 'Editar Papéis',
                                        'delete_roles' => 'Excluir Papéis',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Papel')
                    ->badge()
                    ->color('warning') // Uma cor laranja/amarela para diferenciar
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
            ])
            ->filters([
                // Filtros futuros podem entrar aqui
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\DeleteAction::make()
                        // Protege os papéis vitais do sistema contra exclusão
                        ->hidden(fn(\App\Models\Role $record) => in_array($record->name, ['Master', 'Manager'])),
                ])->tooltip('Ações'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir Selecionados'),
                ]),
            ])
            // Trava a caixinha de seleção em lote para os papéis vitais
            ->checkIfRecordIsSelectableUsing(
                fn(\App\Models\Role $record): bool => !in_array($record->name, ['Master', 'Manager']),
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}