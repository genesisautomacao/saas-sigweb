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
use Illuminate\Support\Facades\Http;

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
                // --- SEÇÃO 1: DADOS PRINCIPAIS ---
                Forms\Components\Section::make('Dados da Prefeitura / Órgão')
                    ->icon('heroicon-o-building-library')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Órgão / Cidade')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Set $set, ?string $state) => $set('slug', Str::slug($state))),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('data.cnpj')
                            ->label('CNPJ')
                            ->mask('99.999.999/9999-99')
                            ->maxLength(20),

                        // A cor base para o tema do cliente
                        Forms\Components\ColorPicker::make('data.color')
                            ->label('Cor Base (Tema do App)')
                            ->default('#3b82f6'),

                        Forms\Components\FileUpload::make('data.logo')
                            ->label('Brasão / Logo')
                            ->image()
                            ->directory('tenant-logos')
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Cadastro Ativo')
                            ->default(true)
                            ->inline(false),
                    ])->columns(2),

                // --- SEÇÃO 2: ENDEREÇO E IBGE (MÁGICA DO VIACEP) ---
                Forms\Components\Section::make('Localização e Integração')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Forms\Components\TextInput::make('data.zip_code')
                            ->label('CEP')
                            ->mask('99999-999')
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (blank($state))
                                    return;

                                $cep = preg_replace('/[^0-9]/', '', $state);
                                if (strlen($cep) !== 8)
                                    return;

                                try {
                                    // 1. Timeout de 4s (Não deixa o sistema travar)
                                    // 2. User-Agent (Evita bloqueio do ViaCEP)
                                    // 3. withoutVerifying (Evita erro de SSL no Laragon/Localhost)
                                    $response = Http::timeout(4)
                                        ->withHeaders(['User-Agent' => 'SaaS-Sigweb-App/1.0'])
                                        ->withoutVerifying()
                                        ->get("https://viacep.com.br/ws/{$cep}/json/");

                                    if ($response->successful() && !isset($response['erro'])) {
                                        $set('data.address', $response['logradouro'] ?? null);
                                        $set('data.neighborhood', $response['bairro'] ?? null);
                                        $set('data.city', $response['localidade'] ?? null);
                                        $set('data.state', $response['uf'] ?? null);
                                        $set('data.ibge_code', $response['ibge'] ?? null);
                                    } else {
                                        \Filament\Notifications\Notification::make()
                                            ->title('CEP não encontrado')
                                            ->warning()
                                            ->send();
                                    }
                                } catch (\Exception $e) {
                                    // Se a API cair ou demorar, o sistema captura o erro silenciosamente
                                    // e avisa o usuário para preencher na mão, sem dar tela de erro 500!
                                    \Filament\Notifications\Notification::make()
                                        ->title('Serviço de CEP instável')
                                        ->body('Por favor, preencha o endereço manualmente.')
                                        ->danger()
                                        ->send();
                                }
                            }),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('data.ibge_code')
                                ->label('Código IBGE')
                                ->required()
                                ->numeric(),
                            Forms\Components\TextInput::make('data.city')
                                ->label('Cidade')
                                ->required(),
                            Forms\Components\TextInput::make('data.state')
                                ->label('Estado (UF)')
                                ->maxLength(2)
                                ->required(),
                        ]),

                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('data.address')
                                ->label('Logradouro (Rua, Av)')
                                ->columnSpan(2),
                            Forms\Components\TextInput::make('data.number')
                                ->label('Número'),
                        ]),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('data.complement')
                                ->label('Complemento'),
                            Forms\Components\TextInput::make('data.neighborhood')
                                ->label('Bairro'),
                        ]),
                    ]),

                // --- SEÇÃO 3: CONFIGURAÇÕES DO MAPA (GIS) ---
                Forms\Components\Section::make('Configurações Geográficas (SIGWEB)')
                    ->icon('heroicon-o-globe-americas')
                    ->schema([
                        Forms\Components\TextInput::make('data.map_lat')
                            ->label('Latitude Central')
                            ->numeric()
                            ->helperText('Ex: -26.9658952'),

                        Forms\Components\TextInput::make('data.map_lon')
                            ->label('Longitude Central')
                            ->numeric()
                            ->helperText('Ex: -50.4182571'),

                        Forms\Components\TextInput::make('data.map_zoom')
                            ->label('Zoom Padrão')
                            ->numeric()
                            ->default(14)
                            ->minValue(1)
                            ->maxValue(22),
                    ])->columns(3),

                // --- SEÇÃO 4: MÓDULOS ---
                Forms\Components\Section::make('Módulos Contratados')
                    ->icon('heroicon-o-squares-2x2')
                    ->schema([
                        Forms\Components\Select::make('modules')
                            ->label('Módulos Liberados para esta Prefeitura')
                            ->multiple()
                            ->options([
                                'administrativo' => 'ADM - Administrativo',
                                'imobiliario' => 'GIS - Cadastro Imobiliário',
                                'arborizacao' => 'GIS - Arborização Urbana',
                                'iluminacao' => 'GIS - Iluminação Pública',
                                'estoque' => 'Estoque',

                                'manutencoes' => 'Mnutenção e Serviços',
                                'cemiterio' => 'GIS - Gestão de Cemitérios',
                                'mob_infra' => 'Mobilidade - Infraestrutura',
                                // Adicione os módulos do CSV que você me mandou aqui!
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

                    // --- NOVA AÇÃO: IMPORTADOR GIS (SUPER ADMIN) ---
                    Tables\Actions\Action::make('importar_gis')
                        ->label('Importar Mapa (GIS)')
                        ->icon('heroicon-o-map')
                        ->color('info')
                        ->form([
                            Forms\Components\Select::make('camada')
                                ->label('Qual camada você está enviando?')
                                ->options([
                                    'PerimetroUrbano' => '1. Perímetros Urbanos',
                                    'Zona' => '2. Zoneamento',
                                    'Bairro' => '3. Bairros',
                                    'Loteamento' => '4. Loteamentos',
                                    'Quadra' => '5. Quadras',
                                    'Logradouro' => '6. Logradouros (Ruas)',
                                    'Lote' => '7. Lotes',
                                    'Edificacao' => '8. Edificações',
                                    'UnidadeImobiliaria' => '9. Unidades Imobiliárias',
                                ])
                                ->required(),
                            Forms\Components\FileUpload::make('arquivo')
                                ->label('Arquivo .json (GeoJSON)')
                                ->acceptedFileTypes(['application/json'])
                                ->maxSize(51200)
                                ->disk('local')
                                ->directory('imports/gis')
                                ->required()
                        ])
                        ->action(function (Tenant $record, array $data) {
                            
                            // Apenas aumenta os limites do servidor para arquivos pesados (14MB+)
                            ini_set('memory_limit', '2048M');
                            set_time_limit(600);

                            $filePath = storage_path('app/private/' . $data['arquivo']);
                            
                            if (!file_exists($filePath)) {
                                \Filament\Notifications\Notification::make()->danger()->title('Arquivo não encontrado no disco!')->send();
                                return;
                            }

                            // LEITURA NATIVA (Sem invenção de moda)
                            $json = json_decode(file_get_contents($filePath));

                            if (json_last_error() !== JSON_ERROR_NONE) {
                                \Filament\Notifications\Notification::make()->danger()->title('Erro ao ler JSON: ' . json_last_error_msg())->send();
                                return;
                            }

                            $features = $json->features ?? [];

                            if (empty($features)) {
                                \Filament\Notifications\Notification::make()->danger()->title('Nenhuma geometria encontrada no arquivo!')->send();
                                return;
                            }

                            $modelClass = "App\\Models\\" . $data['camada'];
                            $agrupados = [];

                            // 1. INTELIGÊNCIA GEOGRÁFICA DE AGRUPAMENTO
                            foreach ($features as $feature) {
                                $props = $feature->properties;
                                $id = $props->id ?? $props->fid ?? uniqid(); 

                                if (!isset($agrupados[$id])) {
                                    $agrupados[$id] = ['props' => $props, 'coords' => [], 'type' => null];
                                }

                                // O SEGREDO ESTÁ AQUI: Proteção real contra propriedades sem mapa (geometry = null)
                                if (isset($feature->geometry) && !empty($feature->geometry) && isset($feature->geometry->type)) {
                                    $geomType = $feature->geometry->type;
                                    
                                    if (in_array($geomType, ['Polygon', 'MultiPolygon'])) {
                                        $agrupados[$id]['type'] = 'MultiPolygon';
                                        if ($geomType === 'Polygon') $agrupados[$id]['coords'][] = $feature->geometry->coordinates;
                                        else foreach ($feature->geometry->coordinates as $poly) $agrupados[$id]['coords'][] = $poly;
                                    }
                                    elseif (in_array($geomType, ['LineString', 'MultiLineString'])) {
                                        $agrupados[$id]['type'] = 'MultiLineString';
                                        if ($geomType === 'LineString') $agrupados[$id]['coords'][] = $feature->geometry->coordinates;
                                        else foreach ($feature->geometry->coordinates as $line) $agrupados[$id]['coords'][] = $line;
                                    }
                                    elseif ($geomType === 'Point') {
                                        $agrupados[$id]['type'] = 'Point';
                                        $agrupados[$id]['coords'] = $feature->geometry->coordinates;
                                    }
                                }
                            }

                            \Illuminate\Support\Facades\DB::beginTransaction();
                            try {
                                foreach ($agrupados as $originalId => $item) {
                                    $props = $item['props'];

                                    // 2. PREENCHIMENTO BASE
                                    $fillData = [
                                        'tenant_id' => $record->id,
                                        'code' => (string) \Illuminate\Support\Str::uuid(),
                                    ];

                                    // TRATAMENTO DA GEOMETRIA (Preenche ou deixa Nulo para imóveis sem mapa)
                                    if (!empty($item['type'])) {
                                        $fillData['geo'] = [
                                            'type' => $item['type'],
                                            'coordinates' => $item['coords']
                                        ];
                                    } else {
                                        $fillData['geo'] = null; 
                                    }

                                    // A REGRA DO NOME (Necessária para Perímetros, Zonas, Logradouros, etc)
                                    $camadasComNome = ['PerimetroUrbano', 'Zona', 'Bairro', 'Loteamento', 'Quadra', 'Logradouro'];
                                    if (in_array($data['camada'], $camadasComNome)) {
                                        $fillData['name'] = $props->name ?? $props->numero_lote ?? 'Sem Nome';
                                    }

                                    // 3. MAPEAMENTO DINÂMICO
                                    if (isset($props->distrito)) $fillData['distrito'] = $props->distrito;
                                    if (isset($props->sigla)) $fillData['sigla'] = $props->sigla;
                                    if (isset($props->rgb)) $fillData['rgb'] = $props->rgb;
                                    if (isset($props->setor)) $fillData['setor'] = $props->setor;

                                    if (isset($props->perimetro_id)) $fillData['perimetro_id'] = $props->perimetro_id;
                                    if (isset($props->bairro_id)) $fillData['bairro_id'] = $props->bairro_id;
                                    if (isset($props->loteamento_id)) $fillData['loteamento_id'] = $props->loteamento_id;
                                    if (isset($props->quadra_id)) $fillData['quadra_id'] = $props->quadra_id;
                                    if (isset($props->zona_id)) $fillData['zona_id'] = $props->zona_id;
                                    if (isset($props->lote_id)) $fillData['lote_id'] = $props->lote_id;

                                    if (isset($props->numero_lot) || isset($props->numero)) $fillData['numero_lote'] = $props->numero_lot ?? $props->numero;
                                    if (isset($props->area_geo)) $fillData['area_geo'] = $props->area_geo;
                                    if (isset($props->main_facade_length)) $fillData['main_facade_length'] = $props->main_facade_length;
                                    if (isset($props->tipo)) $fillData['tipo'] = $props->tipo;
                                    if (isset($props->tp_construcao)) $fillData['tp_construcao'] = $props->tp_construcao;
                                    if (isset($props->caracteristica_construcao)) $fillData['caracteristica_construcao'] = $props->caracteristica_construcao;
                                    if (isset($props->estado_conservacao)) $fillData['estado_conservacao'] = $props->estado_conservacao;
                                    if (isset($props->codigo_imovel_tributario)) $fillData['codigo_imovel_tributario'] = $props->codigo_imovel_tributario;
                                    if (isset($props->inscricao_imobiliaria)) $fillData['inscricao_imobiliaria'] = $props->inscricao_imobiliaria;

                                    // 4. SALVAR NO BANCO
                                    $entidade = new $modelClass();

                                    if (is_numeric($originalId)) {
                                        $entidade->id = $originalId;
                                    }

                                    $entidade->forceFill($fillData)->save();
                                }

                                // 5. CORREÇÃO DE SEQUENCE
                                $tabela = (new $modelClass())->getTable();
                                $maxId = $modelClass::max('id') ?? 0;
                                if ($maxId > 0) {
                                    \Illuminate\Support\Facades\DB::statement("SELECT setval(pg_get_serial_sequence('{$tabela}', 'id'), {$maxId}, false)");
                                }

                                \Illuminate\Support\Facades\DB::commit();
                                
                                // Limpeza do arquivo
                                if (file_exists($filePath)) {
                                    \Illuminate\Support\Facades\Storage::disk('local')->delete($data['arquivo']);
                                }

                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Importação Concluída!')
                                    ->body(count($agrupados) . " registros foram importados para a camada " . $data['camada'])
                                    ->send();

                            } catch (\Exception $e) {
                                \Illuminate\Support\Facades\DB::rollBack();
                                \Filament\Notifications\Notification::make()
                                    ->danger()->title('Erro no Banco de Dados')
                                    ->body($e->getMessage())->send();
                            }
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