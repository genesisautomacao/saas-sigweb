<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeadResource\Pages;
use App\Models\Lead;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\RawJs;

class LeadResource extends Resource
{

    use \App\Traits\HasTenantModule;

    // 2. Define qual é o módulo que esta tela exige
    protected static ?string $tenantModule = 'leads';

    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected static ?string $modelLabel = 'Lead (Prospecção)';
    protected static ?string $pluralModelLabel = 'Leads (Prospecções)';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('LeadTabs')
                    ->tabs([
                        // --- ABA 1: DADOS DA EMPRESA E CONTATO ---
                        Forms\Components\Tabs\Tab::make('Dados da Empresa')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Razão Social / Nome')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('surname')
                                    ->label('Nome Fantasia')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('document')
                                    ->label('CNPJ / CPF')
                                    ->mask(RawJs::make(<<<'JS'
                                        $input.length <= 14 ? '999.999.999-99' : '99.999.999/9999-99'
                                    JS))
                                    // Limpa tudo que não for número antes de salvar no banco
                                    ->dehydrateStateUsing(fn($state) => preg_replace('/[^0-9]/', '', $state))
                                    ->maxLength(18) // 18 é o limite do CNPJ com a máscara
                                    // NOVA REGRA DE VALIDAÇÃO ANTI-DUPLICIDADE MULTI-TENANT
                                    ->rule(function ($record) {
                                        return function (string $attribute, $value, \Closure $fail) use ($record) {
                                            if (blank($value)) return;
                                            
                                            // Limpa a máscara para comparar com o banco
                                            $cleanDoc = preg_replace('/[^0-9]/', '', $value);
                                            $tenant = \Filament\Facades\Filament::getTenant();
                                            
                                            $exists = \App\Models\Lead::where('tenant_id', $tenant?->id)
                                                ->where('document', $cleanDoc)
                                                // Se estiver editando, ignora o próprio registro
                                                ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                                                ->exists();
                                                
                                            if ($exists) {
                                                $fail('Este CPF/CNPJ já está cadastrado em sua base de Leads.');
                                            }
                                        };
                                    }),

                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\TextInput::make('cnae_code')
                                            ->label('Código CNAE'),
                                            
                                        Forms\Components\TextInput::make('cnae_name')
                                            ->label('Descrição do Ramo/CNAE')
                                            ->columnSpan(3)
                                    
                                    ]),

                                Forms\Components\Fieldset::make('Contato Principal')
                                    ->schema([
                                        Forms\Components\TextInput::make('contact_name')
                                            ->label('Nome do Contato')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('email')
                                            ->label('E-mail')
                                            ->email()
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('phone')
                                            ->label('Telefone Fixo')
                                            ->tel()
                                            ->mask(RawJs::make(<<<'JS'
                                                $input.length <= 14 ? '(99) 9999-9999' : '(99) 99999-9999'
                                            JS))
                                            // Limpa tudo que não for número antes de salvar no banco
                                            ->dehydrateStateUsing(fn($state) => preg_replace('/[^0-9]/', '', $state))
                                            ->maxLength(15),

                                        Forms\Components\TextInput::make('whatsapp')
                                            ->label('WhatsApp')
                                            ->tel()
                                            ->mask(RawJs::make(<<<'JS'
                                                $input.length <= 14 ? '(99) 9999-9999' : '(99) 99999-9999'
                                            JS))
                                            ->dehydrateStateUsing(fn($state) => preg_replace('/[^0-9]/', '', $state))
                                            ->maxLength(15),

                                    ])->columns(2),
                            ]),

                        // --- ABA 2: ENDEREÇO (COM VIACEP) ---
                        Forms\Components\Tabs\Tab::make('Endereço')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Forms\Components\TextInput::make('zip_code')
                                    ->label('CEP')
                                    ->mask('99999-999')
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if (blank($state))
                                            return;

                                        $cep = preg_replace('/[^0-9]/', '', $state);
                                        if (strlen($cep) !== 8)
                                            return;

                                        $response = Http::get("https://viacep.com.br/ws/{$cep}/json/");

                                        if ($response->successful() && !isset($response['erro'])) {
                                            $set('address', $response['logradouro'] ?? null);
                                            $set('neighborhood', $response['bairro'] ?? null);
                                            $set('city', $response['localidade'] ?? null);
                                            $set('state', $response['uf'] ?? null);
                                        }
                                    }),

                                Forms\Components\TextInput::make('address')
                                    ->label('Logradouro')
                                    ->maxLength(255),

                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('number')
                                            ->label('Número')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('complement')
                                            ->label('Complemento')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('neighborhood')
                                            ->label('Bairro')
                                            ->maxLength(255),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('city')
                                            ->label('Cidade')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('state')
                                            ->label('Estado (UF)')
                                            ->maxLength(2),
                                    ]),
                            ]),

                        // --- ABA 3: CLASSIFICAÇÃO E FOLLOW-UP ---
                        Forms\Components\Tabs\Tab::make('Classificação Comercial')
                            ->icon('heroicon-o-briefcase')
                            ->schema([

                                /* vendedor DANDO ERRO!!!!!!!!!!!!!!!!!!!!!!!!*/
                                Forms\Components\Select::make('seller_id')
                                    ->label('Vendedor Responsável')
                                    ->options(function () {
                                        $query = \App\Models\Seller::with('user');

                                        /** @var \App\Models\User $user */
                                        $user = \Filament\Facades\Filament::auth()->user();

                                        // Mantemos apenas a sua regra de negócio de vendedor ver só ele mesmo
                                        if ($user && $user->hasPermissionTo('view_my_leads')) {
                                            $query->where('id', $user->seller?->id);
                                        }

                                        return $query->get()->pluck('user.name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->default(function () {
                                        /** @var \App\Models\User $user */
                                        $user = \Filament\Facades\Filament::auth()->user();
                                        return $user->seller?->id;
                                    })
                                    ->disabled(function () {
                                        /** @var \App\Models\User $user */
                                        $user = \Filament\Facades\Filament::auth()->user();
                                        return $user->hasPermissionTo('view_my_leads');
                                    })
                                    ->dehydrated(),

                                Forms\Components\Select::make('lead_status_id')
                                    // Limpamos o tenant_id, mantemos apenas a ordenação do funil!
                                    ->relationship('status', 'name', fn(Builder $query) => $query->orderBy('order', 'asc'))
                                    ->label('Status')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->required(),
                                        Forms\Components\ColorPicker::make('color'),
                                    ]),

                                Forms\Components\Select::make('lead_potential_id')
                                    // Olha como fica limpo! O Global Scope faz o resto sozinho.
                                    ->relationship('potential', 'name')
                                    ->label('Potencial')
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\Select::make('lead_source_id')
                                    // 100% blindado pela Trait BelongsToTenant na Model
                                    ->relationship('source', 'name')
                                    ->label('Origem')
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Anotações Iniciais')
                                    ->columnSpanFull(),
                            ])->columns(2),

                        // --- ABA 4: AGENDAMENTO DE RETORNO ---
                        Forms\Components\Tabs\Tab::make('Agendamento de Retorno')
                            ->icon('heroicon-o-calendar-days')
                            ->schema([
                                Forms\Components\DatePicker::make('last_follow_up_date')
                                    ->label('Agendar Próximo Contato')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->helperText('Selecione a data que o vendedor deve retornar a ligação.'),

                                Forms\Components\TextInput::make('last_follow_up_note')
                                    ->label('Motivo / Observação do Retorno')
                                    ->maxLength(255)
                                    ->helperText('Ex: Ligar de tarde para falar com o gerente.'),
                            ])->columns(2),

                    ]) // <--- AQUI FECHA O ARRAY DE ABAS (O código acima deve ficar DENTRO dele)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequential_id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn($state) => $state ? '#' . str_pad($state, 4, '0', STR_PAD_LEFT) : '-'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Empresa / Contato')
                    ->description(fn(Lead $record): string => $record->contact_name ?? 'Sem contato definido')
                    ->searchable(['name', 'surname', 'contact_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn($record) => $record->status?->color ?? 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('seller.user.name')
                    ->label('Vendedor')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('Local')
                    ->formatStateUsing(fn($record) => $record->city && $record->state ? "{$record->city}/{$record->state}" : '-')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('last_follow_up_date')
                    ->label('Retorno Agendado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color(fn($state) => match (true) {
                        blank($state) => 'gray',
                        \Carbon\Carbon::parse($state)->isPast() && !\Carbon\Carbon::parse($state)->isToday() => 'danger', // Atrasado
                        \Carbon\Carbon::parse($state)->isToday() => 'success', // É para ligar HOJE
                        default => 'gray', // Futuro
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lead_status_id')
                    ->relationship('status', 'name', function (Builder $query) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return $query->where('tenant_id', $tenant?->id)->orderBy('order', 'asc');
                    })
                    ->label('Filtrar por Status')
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('seller_id')
                    ->label('Filtrar por Vendedor')
                    ->options(function () {
                        $tenant = \Filament\Facades\Filament::getTenant();

                        // 1. Isola por Tenant (Segurança Máxima)
                        $query = \App\Models\Seller::with('user')
                            ->where('tenant_id', $tenant?->id);

                        /** @var \App\Models\User $user */
                        $user = \Filament\Facades\Filament::auth()->user();

                        // 2. Se for vendedor restrito, só aparece ele mesmo no filtro
                        if ($user && $user->hasPermissionTo('view_my_leads')) {
                            $query->where('id', $user->seller?->id);
                        }

                        return $query->get()->pluck('user.name', 'id');
                    })
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('follow_up_status')
                    ->label('Status do Retorno')
                    ->options([
                        'hoje' => 'Ligar Hoje',
                        'atrasados' => 'Retornos Atrasados',
                        'futuros' => 'Agendamentos Futuros',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'hoje') {
                            $query->whereDate('last_follow_up_date', now()->toDateString());
                        } elseif ($data['value'] === 'atrasados') {
                            $query->whereDate('last_follow_up_date', '<', now()->toDateString());
                        } elseif ($data['value'] === 'futuros') {
                            $query->whereDate('last_follow_up_date', '>', now()->toDateString());
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])->tooltip('Ações'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Excluir Selecionados'),
                ]),
            ]);
    }

    // --- BLOQUEIO DE VISÃO: BASEADO NA SUA PERMISSÃO ---
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        /** @var \App\Models\User $user */
        $user = \Filament\Facades\Filament::auth()->user();

        // Se o usuário tiver a restrição "ver-meus-leads"
        if ($user->hasPermissionTo('view_my_leads')) {

            $sellerId = $user->seller?->id;

            if ($sellerId) {
                // Filtra a tabela para mostrar APENAS os leads onde ele é o dono
                $query->where('seller_id', $sellerId);
            } else {
                // Bloqueio de segurança caso ele tenha a permissão mas não tenha o perfil de vendedor cadastrado
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            // Aqui futuramente colocaremos a RelationManager de Interações (Follow-ups)
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}