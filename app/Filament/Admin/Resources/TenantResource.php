<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TenantResource\Pages;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $modelLabel = 'Empresa';
    protected static ?string $pluralModelLabel = 'Empresas';
    protected static ?string $navigationGroup = 'Gestão do SaaS';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados Principais')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome da Empresa')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Set $set, ?string $state) => $set('slug', Str::slug($state))),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        // Campos fixos salvos dentro da coluna JSON 'data' usando Dot Notation
                        Forms\Components\TextInput::make('data.cnpj')
                            ->label('CNPJ')
                            ->maxLength(20),

                        Forms\Components\FileUpload::make('data.logo')
                            ->label('Logo da Empresa')
                            ->image()
                            ->directory('tenant-logos') // Salvará na pasta storage/app/public/tenant-logos
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Cadastro Ativo')
                            ->default(true)
                            ->inline(false),
                    ])->columns(2),

                Forms\Components\Section::make('Módulos')
                    ->schema([
                        Forms\Components\Select::make('modules')
                            ->label('Módulos Liberados para esta Tenant')
                            ->multiple()
                            ->options([
                                'leads' => 'CRM - Prospecção de Leads',
                                'frotas' => 'Gestão de Frotas / Abastecimento',
                                'financeiro' => 'Financeiro / Faturamento',
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('data.logo')
                    ->label('Logo')
                    ->circular(), // Mostra a logo redondinha na tabela

                Tables\Columns\TextColumn::make('name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('data.cnpj')
                    ->label('CNPJ')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->actions([
                // Aqui criamos os "3 pontinhos" que agrupam as ações
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),

                    Tables\Actions\DeleteAction::make(),

                    // Nossa ação customizada para criar o Manager
                    Tables\Actions\Action::make('delegar_manager')
                        ->label('Delegar Manager')
                        ->icon('heroicon-o-user-plus')
                        ->color('success')
                        ->form([
                            Forms\Components\TextInput::make('name')
                                ->label('Nome do Manager')
                                ->required(),
                            Forms\Components\TextInput::make('email')
                                ->label('E-mail de Acesso')
                                ->email()
                                ->required()
                                // Garante que não criemos e-mails duplicados na tabela users
                                ->unique(table: 'users', column: 'email'),
                            Forms\Components\TextInput::make('password')
                                ->label('Senha')
                                ->password()
                                ->required()
                                ->minLength(8),
                        ])
                        ->action(function (Tenant $record, array $data) {
                            $user = User::create([
                                'name' => $data['name'],
                                'email' => $data['email'],
                                'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
                                'email_verified_at' => now(),
                            ]);

                            // 1. Vincula o usuário à Tenant primeiro
                            $record->users()->attach($user->id);

                            // 2. Avisamos o Spatie em qual Tenant estamos operando
                            setPermissionsTeamId($record->id);

                            // 3. Atribui o papel (agora ele vai achar o Manager exclusivo desta Tenant)
                            $user->assignRole('Manager');

                            \Filament\Notifications\Notification::make()
                                ->title('Manager criado e vinculado com sucesso!')
                                ->success()
                                ->send();
                        }),

                ])->tooltip('Ações'),
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}