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
                            return $rule->where('tenant_id', $tenant?->id);
                        }
                    ),

                Forms\Components\Section::make('Permissões de Acesso')
                    ->description('Selecione as permissões organizadas por módulo do sistema.')
                    ->schema([

                        // CAIXA 1: USUÁRIOS E VENDEDORES
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

                        // CAIXA 2: PAPÉIS
                        Forms\Components\Fieldset::make('Módulo: Segurança (Papéis)')
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

                        // CAIXA 3: ADMINISTRATIVO - PESSOAS
                        Forms\Components\Fieldset::make('Módulo Administrativo: Pessoas')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_pessoas')
                                    ->label('Gestão de Pessoas')
                                    ->options([
                                        'view_pessoas' => 'Visualizar Pessoas',
                                        'create_pessoas' => 'Criar Pessoas',
                                        'edit_pessoas' => 'Editar Pessoas',
                                        'delete_pessoas' => 'Excluir Pessoas',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 4: ADMINISTRATIVO - CONTATOS
                        Forms\Components\Fieldset::make('Módulo Administrativo: Contatos')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_contatos')
                                    ->label('Gestão de Contatos')
                                    ->options([
                                        'view_contatos' => 'Visualizar Contatos',
                                        'create_contatos' => 'Criar Contatos',
                                        'edit_contatos' => 'Editar Contatos',
                                        'delete_contatos' => 'Excluir Contatos',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 5: ADMINISTRATIVO - ENDEREÇOS
                        Forms\Components\Fieldset::make('Módulo Administrativo: Endereços')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_enderecos')
                                    ->label('Gestão de Endereços')
                                    ->options([
                                        'view_enderecos' => 'Visualizar Endereços',
                                        'create_enderecos' => 'Criar Endereços',
                                        'edit_enderecos' => 'Editar Endereços',
                                        'delete_enderecos' => 'Excluir Endereços',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 6: ADMINISTRATIVO - DOCUMENTOS
                        Forms\Components\Fieldset::make('Módulo Administrativo: Documentos')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_documentos')
                                    ->label('Gestão de Documentos')
                                    ->options([
                                        'view_documentos' => 'Visualizar Documentos',
                                        'create_documentos' => 'Criar Documentos',
                                        'edit_documentos' => 'Editar Documentos',
                                        'delete_documentos' => 'Excluir Documentos',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // CAIXA 7: ILUMINAÇÃO PÚBLICA
                        Forms\Components\Fieldset::make('Módulo: Iluminação Pública')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_iluminacao')
                                    ->label('Gestão de Iluminação')
                                    ->options([
                                        'view_tipos_poste' => 'Visualizar Tipos',
                                        'create_tipos_poste' => 'Criar Tipos',
                                        'edit_tipos_poste' => 'Editar Tipos',
                                        'delete_tipos_poste' => 'Excluir Tipos',
                                        'view_postes' => 'Visualizar Postes',
                                        'create_postes' => 'Criar Postes',
                                        'edit_postes' => 'Editar Postes',
                                        'delete_postes' => 'Excluir Postes',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                        // 🛑 CAIXA 8: MEIO AMBIENTE / ARBORIZAÇÃO
                        Forms\Components\Fieldset::make('Módulo: Meio Ambiente (Árvores)')
                            ->schema([
                                Forms\Components\CheckboxList::make('permissions_arborizacao')
                                    ->label('Gestão de Árvores')
                                    ->options([
                                        'view_arvores' => 'Visualizar Árvores',
                                        'create_arvores' => 'Criar Árvores',
                                        'edit_arvores' => 'Editar Árvores',
                                        'delete_arvores' => 'Excluir Árvores',
                                    ])
                                    ->bulkToggleable(),
                            ])->columns(1)->columnSpan(1),

                    ])->columns(4), // 🛑 Mudou de 3 para 4 colunas para as 8 caixas ficarem perfeitas

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome do Papel')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->hidden(fn(\App\Models\Role $record) => in_array($record->name, ['Master', 'Manager'])),
                ])->tooltip('Ações'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir Selecionados'),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn(\App\Models\Role $record): bool => !in_array($record->name, ['Master', 'Manager']),
            );
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