<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UsuarioAdminResource\Pages;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuários do painel /admin (super administradores e operadores do SaaS).
 *
 * Visível SOMENTE para o Master — é ele quem cria, edita e exclui operadores e marca,
 * usuário a usuário, o que cada um pode fazer (User::CAPACIDADES_ADMIN). Os vínculos
 * são globais (roles/permissões com tenant_id = null) e nunca tocam nos papéis que a
 * pessoa tenha dentro de uma prefeitura — ver User::sincronizarAcessoAdmin().
 */
class UsuarioAdminResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $modelLabel = 'Usuário do Admin';

    protected static ?string $pluralModelLabel = 'Usuários do Admin';

    protected static ?string $navigationGroup = 'Configurações Globais';

    protected static ?string $slug = 'usuarios-admin';

    protected static ?int $navigationSort = 1;

    /** Descrição de cada capacidade (a chave e o rótulo vivem em User::CAPACIDADES_ADMIN). */
    public const DESCRICOES_CAPACIDADES = [
        'admin_editar_prefeitura' => 'Nome, brasão, cor, endereço, enquadramento do mapa e camadas do app.',
        'admin_criar_prefeitura' => 'Cadastrar uma nova prefeitura no SaaS.',
        'admin_gerenciar_modulos' => 'Definir quais módulos cada prefeitura enxerga no sistema.',
        'admin_importar_gis' => 'Enviar o GeoJSON das camadas (lotes, quadras, bairros…).',
        'admin_recalcular_gis' => 'Recalcular áreas e extensões via PostGIS após a importação.',
        'admin_tributario_simulacao' => 'Enviar/substituir o JSON que alimenta a integração simulada.',
        'admin_tributario_sincronizar' => 'Rodar a sincronização em massa das unidades imobiliárias.',
        'admin_sincronizar_esus' => 'Acionar o ETL de saúde (e-SUS AB) da prefeitura.',
        'admin_delegar_manager' => 'Criar o usuário gestor (Manager) de uma prefeitura.',
    ];

    // ---- Acesso: exclusivo do Master (não depende da UserPolicy, que serve ao painel da prefeitura) ----

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->isMaster() ?? false;
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    /** Só os usuários com papel GLOBAL do SaaS aparecem aqui (equipe de prefeitura fica de fora). */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', fn (Builder $q) => $q
                ->whereNull('roles.tenant_id')
                ->whereIn('roles.name', User::PAPEIS_ADMIN));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados de acesso')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail (login)')
                            ->email()
                            ->required()
                            ->unique(table: 'users', column: 'email', ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->helperText(fn (string $context) => $context === 'edit' ? 'Deixe em branco para manter a senha atual.' : null)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Nível de acesso')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        Forms\Components\Radio::make('papel_admin')
                            ->label('Papel no painel do SaaS')
                            ->options([
                                User::PAPEL_MASTER => 'Master (super administrador)',
                                User::PAPEL_OPERADOR => 'Operador (acesso restrito)',
                            ])
                            ->descriptions([
                                User::PAPEL_MASTER => 'Acesso irrestrito: exclui prefeituras, configura APIs, sistemas tributários e gerencia estes usuários.',
                                User::PAPEL_OPERADOR => 'Só faz o que estiver marcado abaixo. Nunca exclui prefeitura nem enxerga credenciais de integração.',
                            ])
                            ->default(User::PAPEL_OPERADOR)
                            ->required()
                            ->live()
                            ->dehydrated(false)
                            // Trava de segurança: ninguém rebaixa a si mesmo e perde o painel.
                            ->disabled(fn (?User $record) => $record?->getKey() === Filament::auth()->id())
                            ->helperText(fn (?User $record) => $record?->getKey() === Filament::auth()->id()
                                ? 'Você não pode alterar o seu próprio nível de acesso.'
                                : null),

                        Forms\Components\CheckboxList::make('capacidades')
                            ->label('O que este operador pode fazer')
                            ->options(User::CAPACIDADES_ADMIN)
                            ->descriptions(self::DESCRICOES_CAPACIDADES)
                            ->columns(2)
                            ->dehydrated(false)
                            ->bulkToggleable()
                            ->visible(fn (Forms\Get $get) => $get('papel_admin') === User::PAPEL_OPERADOR)
                            ->helperText('Cada caixa libera um botão do painel. Sem nenhuma marcada, o operador só consegue consultar a lista de prefeituras.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('papel')
                    ->label('Papel')
                    ->badge()
                    ->state(fn (User $record) => $record->papelAdmin() ?? '—')
                    ->color(fn (?string $state) => $state === User::PAPEL_MASTER ? 'danger' : 'info'),

                Tables\Columns\TextColumn::make('capacidades')
                    ->label('Capacidades')
                    ->state(function (User $record): string {
                        if ($record->isMaster()) {
                            return 'Acesso total';
                        }

                        $total = count($record->capacidadesAdmin());

                        return $total === 0 ? 'Somente consulta' : $total.' liberada(s)';
                    })
                    ->tooltip(fn (User $record) => $record->isMaster()
                        ? null
                        : (collect($record->capacidadesAdmin())
                            ->map(fn ($c) => User::CAPACIDADES_ADMIN[$c] ?? $c)
                            ->implode(' · ') ?: null))
                    ->badge()
                    ->color(fn (User $record) => $record->isMaster() ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->label('Excluir')
                    // Não deixa o Master excluir a própria conta.
                    ->hidden(fn (User $record) => $record->getKey() === Filament::auth()->id()),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Nenhum usuário do painel')
            ->emptyStateDescription('Crie operadores para delegar tarefas como importar mapa GIS ou atualizar dados das prefeituras, sem dar acesso total ao SaaS.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsuarioAdmins::route('/'),
            'create' => Pages\CreateUsuarioAdmin::route('/create'),
            'edit' => Pages\EditUsuarioAdmin::route('/{record}/edit'),
        ];
    }
}
