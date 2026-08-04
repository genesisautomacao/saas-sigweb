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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $modelLabel = 'Prefeitura';

    protected static ?string $pluralModelLabel = 'Prefeituras';

    protected static ?string $navigationGroup = 'Gestão do SaaS';

    /**
     * Atalho das travas do painel: Master vê tudo; Operador só o que foi marcado
     * no cadastro dele (Configurações Globais → Usuários do Admin).
     */
    protected static function pode(string $capacidade): bool
    {
        return \Filament\Facades\Filament::auth()->user()?->podeNoAdmin($capacidade) ?? false;
    }

    protected static function ehMaster(): bool
    {
        return \Filament\Facades\Filament::auth()->user()?->isMaster() ?? false;
    }

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
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

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

                // --- SEÇÃO 2: INTEGRAÇÃO E-SUS AB ---
                Forms\Components\Section::make('Integração e-SUS AB (Saúde)')
                    ->icon('heroicon-o-heart')
                    ->description('Configurações de sincronização diária do Cadastro Único e Saúde.')
                    // Credenciais de integração: só o Master. O EditTenant preserva as
                    // chaves ocultas do JSON `data` no save (mutateFormDataBeforeSave).
                    ->visible(fn () => static::ehMaster())
                    ->schema([
                        // 🟢 O BOTÃO MÁGICO PARA A SUA APRESENTAÇÃO
                        Forms\Components\Toggle::make('data.esus_simulacao')
                            ->label('Ativar Modo Simulação (Apresentação)')
                            ->helperText('Se ativo, o ETL usará um pacote de dados local (JSON fake) para demonstrar a sincronização, ignorando a URL e o Token.')
                            ->live() // Necessário para esconder os campos abaixo dinamicamente
                            ->default(false)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('data.esus_url')
                            ->label('URL da API do Servidor PEC')
                            ->placeholder('Ex: https://esus.santacecilia.sc.gov.br/api')
                            ->url()
                            ->hidden(fn (Forms\Get $get) => $get('data.esus_simulacao')) // 🪄 Esconde se for simulação
                            ->required(fn (Forms\Get $get) => ! $get('data.esus_simulacao')), // 🪄 Obriga só se não for simulação

                        Forms\Components\TextInput::make('data.esus_token')
                            ->label('Token de Acesso (API Key)')
                            ->password()
                            ->revealable()
                            ->hidden(fn (Forms\Get $get) => $get('data.esus_simulacao'))
                            ->required(fn (Forms\Get $get) => ! $get('data.esus_simulacao')),
                    ])->columns(2),

                // --- SEÇÃO 3: ENDEREÇO E IBGE (MÁGICA DO VIACEP) ---
                Forms\Components\Section::make('Localização e Integração')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Forms\Components\TextInput::make('data.zip_code')
                            ->label('CEP')
                            ->mask('99999-999')
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (blank($state)) {
                                    return;
                                }

                                $cep = preg_replace('/[^0-9]/', '', $state);
                                if (strlen($cep) !== 8) {
                                    return;
                                }

                                try {
                                    // 1. Timeout de 4s (Não deixa o sistema travar)
                                    // 2. User-Agent (Evita bloqueio do ViaCEP)
                                    // 3. withoutVerifying (Evita erro de SSL no Laragon/Localhost)
                                    $response = Http::timeout(4)
                                        ->withHeaders(['User-Agent' => 'SaaS-Sigweb-App/1.0'])
                                        ->withoutVerifying()
                                        ->get("https://viacep.com.br/ws/{$cep}/json/");

                                    if ($response->successful() && ! isset($response['erro'])) {
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

                // --- SEÇÃO 4: CONFIGURAÇÕES DO MAPA (GIS) ---
                Forms\Components\Section::make('Configurações Geográficas (SIGWEB)')
                    ->icon('heroicon-o-globe-americas')
                    ->schema([
                        // dehydrateStateUsing: TextInput numeric() entrega STRING — grava como
                        // NÚMERO no JSON tenant.data (o app mobile/react-native-maps exige número).
                        Forms\Components\TextInput::make('data.map_lat')
                            ->label('Latitude Central')
                            ->numeric()
                            ->dehydrateStateUsing(fn ($state) => is_numeric($state) ? (float) $state : null)
                            ->helperText('Ex: -26.9658952'),

                        Forms\Components\TextInput::make('data.map_lon')
                            ->label('Longitude Central')
                            ->numeric()
                            ->dehydrateStateUsing(fn ($state) => is_numeric($state) ? (float) $state : null)
                            ->helperText('Ex: -50.4182571'),

                        Forms\Components\TextInput::make('data.map_zoom')
                            ->label('Zoom Padrão')
                            ->numeric()
                            ->default(14)
                            ->minValue(1)
                            ->maxValue(22)
                            ->dehydrateStateUsing(fn ($state) => is_numeric($state) ? (int) $state : null),

                        // Item 179 (PoC): camadas que o app móvel poderá exibir.
                        // Lidas por GET /api/map/layers (gravadas em tenant.data['mobile_layers']).
                        Forms\Components\CheckboxList::make('data.mobile_layers')
                            ->label('Camadas visíveis no aplicativo móvel')
                            ->helperText('Selecione quais camadas o app poderá exibir. Se nenhuma for marcada, o app mostra todas as camadas dos módulos ativos.')
                            ->options([
                                'lotes' => 'Lotes',
                                'quadras' => 'Quadras',
                                'bairros' => 'Bairros',
                                'logradouros' => 'Logradouros',
                                'zonas' => 'Zonas',
                                'arvores' => 'Árvores (módulo Arborização)',
                                'postes' => 'Postes (módulo Iluminação)',
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])->columns(3),

                // --- SEÇÃO 5: INTEGRAÇÃO TRIBUTÁRIA (R67-5) ---
                // O de/para de campos vive no catálogo global (Configurações Globais →
                // Sistemas Tributários); aqui só se aponta QUAL sistema a prefeitura usa.
                Forms\Components\Section::make('Integração Tributária')
                    ->icon('heroicon-o-arrows-right-left')
                    ->description('Sistema de arrecadação usado pela prefeitura. O mapa de campos (de/para) é parametrizado uma única vez por sistema em "Configurações Globais → Sistemas Tributários".')
                    // Sistema, modo simulação/produção e credenciais da API: só o Master.
                    ->visible(fn () => static::ehMaster())
                    ->schema([
                        Forms\Components\Select::make('data.sistema_tributario_id')
                            ->label('Sistema tributário desta prefeitura')
                            ->placeholder('Nenhum (nomes de campo já no padrão SIGWEB)')
                            ->options(fn () => \App\Models\SistemaTributario::where('ativo', true)->orderBy('nome')->pluck('nome', 'id'))
                            ->searchable()
                            ->nullable()
                            ->live()
                            // tenant.data é JSON: grava como número (ou remove a chave)
                            ->dehydrateStateUsing(fn ($state) => is_numeric($state) ? (int) $state : null)
                            ->helperText('As importações e sincronizações tributárias desta prefeitura passam a usar o de/para do sistema escolhido.'),

                        // 🔑 A CHAVE simulação ↔ produção. "Produção" exige que o sistema
                        // escolhido tenha CONECTOR implementado (coluna driver do catálogo
                        // registrada em IntegraPrefeituraService::DRIVERS).
                        Forms\Components\Select::make('data.tributario_modo')
                            ->label('Modo da integração')
                            ->options([
                                'simulacao' => 'Simulação (JSON enviado pelo admin)',
                                'producao' => 'Produção (API real do sistema)',
                            ])
                            ->default('simulacao')
                            ->live()
                            ->disableOptionWhen(function (string $value, Forms\Get $get): bool {
                                if ($value !== 'producao') {
                                    return false;
                                }
                                $sistemaId = $get('data.sistema_tributario_id');
                                $driver = $sistemaId ? \App\Models\SistemaTributario::find($sistemaId)?->driver : null;

                                return ! ($driver && array_key_exists($driver, \App\Services\ApiTools\IntegraPrefeituraService::DRIVERS));
                            })
                            ->helperText('"Produção" só fica disponível quando o sistema escolhido tem conector de API implementado.'),

                        // Credenciais da INSTÂNCIA desta prefeitura (o conector é do sistema;
                        // URL e token são de cada cliente) — mesmo padrão da seção e-SUS.
                        Forms\Components\TextInput::make('data.tributario_api.url')
                            ->label('URL da API do sistema tributário')
                            ->placeholder('Ex: https://tributos.prefeitura.gov.br/api')
                            ->url()
                            ->visible(fn (Forms\Get $get) => $get('data.tributario_modo') === 'producao')
                            ->required(fn (Forms\Get $get) => $get('data.tributario_modo') === 'producao'),

                        Forms\Components\TextInput::make('data.tributario_api.token')
                            ->label('Token / chave de acesso')
                            ->password()
                            ->revealable()
                            ->visible(fn (Forms\Get $get) => $get('data.tributario_modo') === 'producao')
                            ->required(fn (Forms\Get $get) => $get('data.tributario_modo') === 'producao'),

                        // Status da fonte de dados (o upload fica na AÇÃO "Simulação Tributária" da listagem)
                        Forms\Components\Placeholder::make('simulacao_status')
                            ->label('Fonte de dados (simulação)')
                            ->visible(fn (Forms\Get $get) => $get('data.tributario_modo') !== 'producao')
                            ->content(function (?Tenant $record): string {
                                if (! $record) {
                                    return 'Salve a prefeitura para enviar o JSON de simulação.';
                                }

                                $resumo = \App\Services\ApiTools\IntegraPrefeituraService::resumoMock($record);

                                return $resumo
                                    ? "Arquivo com {$resumo['imoveis']} imóvel(is) — atualizado em {$resumo['atualizado_em']->format('d/m/Y H:i')}. Substitua pela ação \"Simulação Tributária\" na listagem."
                                    : 'Nenhum arquivo enviado — use a ação "Simulação Tributária" na listagem de prefeituras.';
                            }),
                    ])->columns(2),

                // --- SEÇÃO 6: MÓDULOS ---
                Forms\Components\Section::make('Módulos Contratados')
                    ->icon('heroicon-o-squares-2x2')
                    ->visible(fn () => static::pode('admin_gerenciar_modulos'))
                    ->schema([
                        Forms\Components\Select::make('modules')
                            ->label('Módulos Liberados para esta Prefeitura')
                            ->multiple()
                            ->options([
                                'administrativo' => 'ADM - Administrativo',
                                'imobiliario' => 'GIS - Cadastro Imobiliário',
                                'arborizacao' => 'GIS - Arborização Urbana',
                                'iluminacao' => 'GIS - Iluminação Pública',
                                'estoque' => 'ADM - Estoque',
                                'manutencao' => 'ADM - Manutenção e Serviços',
                                'cemiterio' => 'GIS - Gestão de Cemitérios',
                                'social' => 'ADM - Cadastro Social',
                                'pgv' => 'PGV - Planta Genérica de Valores',
                                'processos' => 'BPMN - Processos Digitais',
                                'rural' => 'GIS - Módulo Rural',
                                'patrimonios' => 'ADM - Patrimônios Públicos',
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
                    ->label('Prefeitura')
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

                    // PT-1 — landing pública de links do município (para o site da prefeitura)
                    Tables\Actions\Action::make('link_portal')
                        ->label('Link do Portal')
                        ->icon('heroicon-o-link')
                        ->color('info')
                        ->url(fn (Tenant $record) => url('/portal/'.$record->slug))
                        ->openUrlInNewTab()
                        ->tooltip('Abre a página de links do município — é este endereço que a prefeitura divulga no site oficial.'),

                    // 🟢 BOTÃO DE SINCRONIZAÇÃO E-SUS
                    Tables\Actions\Action::make('syncEsus')
                        ->label('Sincronizar e-SUS')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->visible(fn () => static::pode('admin_sincronizar_esus'))
                        ->requiresConfirmation()
                        ->modalHeading('Sincronizar dados do e-SUS')
                        ->modalDescription('Deseja acionar o motor de ETL para buscar as atualizações de saúde do Cadastro Único no servidor do e-SUS agora?')
                        ->modalSubmitActionLabel('Sim, Iniciar Sincronização')
                        ->action(function (Tenant $record) {
                            // Chama o nosso serviço
                            $service = app(\App\Services\ApiTools\EsusSyncService::class);
                            $sucesso = $service->syncTenant($record);

                            if ($sucesso) {
                                Notification::make()
                                    ->success()
                                    ->title('Sincronização Concluída!')
                                    ->body('Os cadastros e condições de saúde foram atualizados com sucesso.')
                                    ->send();
                            } else {
                                Notification::make()
                                    ->danger()
                                    ->title('Sincronização Falhou')
                                    ->body('Verifique as credenciais da API ou os logs do sistema.')
                                    ->send();
                            }
                        })->tooltip('Baixar dados do SUS'),

                    // Nossa ação customizada para criar o Manager
                    Tables\Actions\Action::make('delegar_manager')
                        ->label('Delegar Manager')
                        ->icon('heroicon-o-user-plus')
                        ->color('success')
                        ->visible(fn () => static::pode('admin_delegar_manager'))
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
                    // --- NOVA AÇÃO: IMPORTADOR GIS (SUPER ADMIN) ---
                    Tables\Actions\Action::make('importar_gis')
                        ->label('Importar Mapa (GIS)')
                        ->icon('heroicon-o-map')
                        ->color('info')
                        ->visible(fn () => static::pode('admin_importar_gis'))
                        ->form(fn (Tenant $record) => [
                            Forms\Components\Select::make('camada')
                                ->label('Qual camada você está enviando?')
                                ->options([
                                    'PerimetroUrbano' => '1. Distritos / Limites',
                                    'Zona' => '2. Zoneamento',
                                    'Bairro' => '3. Bairros',
                                    'Loteamento' => '4. Loteamentos',
                                    'Quadra' => '5. Quadras',
                                    'Logradouro' => '6. Logradouros (Ruas)',
                                    'Lote' => '7. Lotes',
                                    'Edificacao' => '8. Edificações',
                                    'UnidadeImobiliaria' => '9. Unidades Imobiliárias',
                                ])
                                ->live()
                                ->required(),

                            // T2.6 — modos de reimportação (upsert / substituição)
                            Forms\Components\Select::make('modo')
                                ->label('Modo de importação')
                                ->options([
                                    'adicionar' => 'Adicionar — todos os registros do arquivo entram como novos',
                                    'atualizar' => 'Atualizar — casa pelo id do arquivo: atualiza os existentes e cria os novos',
                                    'substituir' => 'Substituir — apaga os registros atuais da camada e importa do zero',
                                ])
                                ->default('adicionar')
                                ->required()
                                ->live()
                                ->helperText('Atualizar e Substituir casam os registros pelo id do arquivo (sequential_id). "Atualizar" é o indicado após editar a cartografia no QGIS — preserva todos os vínculos.'),

                            Forms\Components\Placeholder::make('aviso_substituir')
                                ->hiddenLabel()
                                ->visible(fn (Forms\Get $get) => $get('modo') === 'substituir')
                                ->content(function (Forms\Get $get) use ($record): \Illuminate\Support\HtmlString {
                                    $camada = $get('camada');
                                    $total = null;

                                    if ($camada && class_exists('App\\Models\\'.$camada)) {
                                        $modelClass = 'App\\Models\\'.$camada;
                                        $total = $modelClass::withoutGlobalScopes()
                                            ->where('tenant_id', $record->id)
                                            ->whereNull('deleted_at')
                                            ->count();
                                    }

                                    return new \Illuminate\Support\HtmlString(
                                        '<div style="border:1px solid #f59e0b;background:#fffbeb;color:#92400e;border-radius:8px;padding:10px 12px;font-size:13px;">'
                                        .'⚠️ <strong>'.($total !== null ? number_format($total, 0, ',', '.').' registro(s) atuais' : 'Todos os registros atuais')
                                        .' desta camada irão para a lixeira</strong> (soft delete, recuperável) antes da importação. '
                                        .'Vínculos de unidades, edificações, testadas, coletas e processos digitais são <strong>reconectados automaticamente</strong> '
                                        .'aos novos registros de mesmo id. Se a importação falhar, nada é apagado (transação única).'
                                        .'</div>'
                                    );
                                }),

                            Forms\Components\FileUpload::make('arquivo')
                                ->label('Arquivo .json (GeoJSON)')
                                ->acceptedFileTypes(['application/json'])
                                ->maxSize(51200)
                                ->disk('local')
                                ->directory('imports/gis')
                                ->required(),
                        ])
                        ->action(function (Tenant $record, array $data) {

                            // Apenas aumenta os limites do servidor para arquivos pesados (14MB+)
                            ini_set('memory_limit', '2048M');
                            set_time_limit(600);

                            $filePath = storage_path('app/private/'.$data['arquivo']);

                            if (! file_exists($filePath)) {
                                \Filament\Notifications\Notification::make()->danger()->title('Arquivo não encontrado no disco!')->send();

                                return;
                            }

                            // LEITURA NATIVA (Sem invenção de moda)
                            $json = json_decode(file_get_contents($filePath));

                            if (json_last_error() !== JSON_ERROR_NONE) {
                                \Filament\Notifications\Notification::make()->danger()->title('Erro ao ler JSON: '.json_last_error_msg())->send();

                                return;
                            }

                            $features = $json->features ?? [];

                            if (empty($features)) {
                                \Filament\Notifications\Notification::make()->danger()->title('Nenhuma geometria encontrada no arquivo!')->send();

                                return;
                            }

                            // T2.6 — todo o motor (agrupamento, modos adicionar/atualizar/
                            // substituir, campos custom e reconexão) vive no service testável.
                            try {
                                $resumo = \App\Services\Gis\ImportadorGisService::importar(
                                    $record,
                                    $data['camada'],
                                    (array) $features,
                                    $data['modo'] ?? 'adicionar',
                                );
                            } catch (\Throwable $e) {
                                \Filament\Notifications\Notification::make()
                                    ->danger()->title('Erro no Banco de Dados')
                                    ->body($e->getMessage())->send();

                                return;
                            }

                            // Limpeza do arquivo
                            if (file_exists($filePath)) {
                                \Illuminate\Support\Facades\Storage::disk('local')->delete($data['arquivo']);
                            }

                            $corpo = $resumo['total'].' registros processados na camada '.$data['camada']
                                .' ('.$resumo['criados'].' novo(s)'
                                .($resumo['atualizados'] > 0 ? ', '.$resumo['atualizados'].' atualizado(s)' : '')
                                .').';

                            if ($resumo['apagados'] > 0) {
                                $corpo .= ' '.$resumo['apagados'].' registro(s) anteriores foram para a lixeira (recuperáveis).';
                            }

                            if ($resumo['reconectados'] !== []) {
                                $corpo .= ' Vínculos reconectados: '
                                    .collect($resumo['reconectados'])->map(fn ($qtd, $tabela) => "{$qtd} × {$tabela}")->implode(', ').'.';
                            }

                            // Vínculos que o JSON pedia e não existem nesta prefeitura: quase sempre
                            // é a hierarquia importada fora de ordem (quadras antes dos lotes, etc.).
                            if ($resumo['nao_resolvidos'] !== []) {
                                $corpo .= ' ⚠️ Referências não encontradas (vínculo ficou vazio): '
                                    .collect($resumo['nao_resolvidos'])->map(fn ($qtd, $rotulo) => "{$qtd} × {$rotulo}")->implode(', ')
                                    .'. Importe a camada superior primeiro e reimporte esta.';
                            }

                            $notificacao = \Filament\Notifications\Notification::make()
                                ->title('Importação Concluída!')
                                ->body($corpo);

                            // Aviso fica na tela até o usuário fechar (não pode passar batido)
                            $resumo['nao_resolvidos'] === []
                                ? $notificacao->success()->send()
                                : $notificacao->warning()->persistent()->send();
                        }),

                    // --- AÇÃO: SIMULAÇÃO TRIBUTÁRIA (R67-5) ---
                    // Upload do JSON que alimenta a integração tributária em modo simulação
                    // (storage/app/mocks/{slug}.json — lido pelo IntegraPrefeituraService nos
                    // botões ☁️/Sincronizar da prefeitura) + importação em massa opcional.
                    Tables\Actions\Action::make('simulacao_tributaria')
                        ->label('Simulação Tributária')
                        ->icon('heroicon-o-document-currency-dollar')
                        ->color('warning')
                        ->visible(fn () => static::pode('admin_tributario_simulacao'))
                        ->modalHeading(fn (Tenant $record) => "Simulação Tributária — {$record->name}")
                        ->modalDescription('O JSON enviado vira a "API da prefeitura": os botões ☁️ e Sincronizar passam a responder com estes dados, já traduzidos pelo de/para do sistema tributário configurado.')
                        ->form(fn (Tenant $record) => [
                            Forms\Components\Placeholder::make('status_atual')
                                ->label('Situação atual')
                                ->content(function () use ($record): string {
                                    $resumo = \App\Services\ApiTools\IntegraPrefeituraService::resumoMock($record);
                                    $sistemaId = data_get($record->data, 'sistema_tributario_id');
                                    $sistema = $sistemaId ? \App\Models\SistemaTributario::find($sistemaId)?->nome : null;

                                    return ($resumo
                                        ? "Arquivo atual: {$resumo['imoveis']} imóvel(is), atualizado em {$resumo['atualizado_em']->format('d/m/Y H:i')}."
                                        : 'Nenhum arquivo de simulação enviado ainda.')
                                        .' Sistema tributário: '.($sistema ?? 'nenhum (campos no padrão SIGWEB)').'.';
                                }),

                            Forms\Components\FileUpload::make('arquivo')
                                ->label('JSON de simulação (substitui o atual)')
                                ->helperText('Array de imóveis ou {"imoveis": [...]}. Pode usar os nomes de campo do sistema de origem — o de/para traduz na entrada.')
                                ->acceptedFileTypes(['application/json'])
                                ->maxSize(51200)
                                ->disk('local')
                                ->directory('imports/tributario')
                                ->nullable(),

                            Forms\Components\Toggle::make('importar_agora')
                                ->label('Importar todos os imóveis agora')
                                ->helperText('Roda a importação em massa (tributario:importar) em cima do arquivo, gravando nas unidades imobiliárias por inscrição. Sem isto, o arquivo só alimenta a busca por código (☁️/Sincronizar).')
                                ->default(false),
                        ])
                        ->action(function (Tenant $record, array $data) {
                            $mockPath = \App\Services\ApiTools\IntegraPrefeituraService::caminhoMock($record);
                            $mensagens = [];

                            // 1) Upload novo? Valida, normaliza (raiz = array) e salva como o mock do tenant
                            if (! empty($data['arquivo'])) {
                                $tmpPath = storage_path('app/private/'.$data['arquivo']);

                                if (! file_exists($tmpPath)) {
                                    Notification::make()->danger()->title('Arquivo não encontrado no disco!')->send();

                                    return;
                                }

                                $json = json_decode(file_get_contents($tmpPath), true);
                                $imoveis = is_array($json) ? (isset($json[0]) ? $json : ($json['imoveis'] ?? null)) : null;

                                if (! is_array($imoveis) || $imoveis === [] || ! is_array($imoveis[0] ?? null)) {
                                    Notification::make()->danger()
                                        ->title('JSON inválido')
                                        ->body('Esperado um array de imóveis (ou {"imoveis": [...]}) com ao menos 1 registro.')
                                        ->send();

                                    return;
                                }

                                \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($mockPath));
                                file_put_contents($mockPath, json_encode($imoveis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                \Illuminate\Support\Facades\Storage::disk('local')->delete($data['arquivo']);

                                // Diagnóstico: quantos ficam localizáveis depois do de/para?
                                $comInscricao = 0;
                                $comCodigo = 0;
                                foreach ($imoveis as $imovel) {
                                    $traduzido = \App\Services\Fiscal\MapaFiscalService::aplicar((array) $imovel, $record->id);
                                    filled($traduzido['inscricao_imobiliaria'] ?? null) && $comInscricao++;
                                    filled($traduzido['codigo_imovel_tributario'] ?? null) && $comCodigo++;
                                }

                                $mensagens[] = count($imoveis).' imóvel(is) salvos na simulação — '
                                    ."{$comCodigo} com código tributário e {$comInscricao} com inscrição (após o de/para).";
                            }

                            if (! file_exists($mockPath)) {
                                Notification::make()->warning()
                                    ->title('Nenhum arquivo de simulação')
                                    ->body('Envie o JSON para ativar a integração simulada desta prefeitura.')
                                    ->send();

                                return;
                            }

                            // 2) Importação em massa opcional (grava nas unidades por inscrição)
                            if (! empty($data['importar_agora'])) {
                                \App\Services\Fiscal\MapaFiscalService::limparCache();

                                $exitCode = \Illuminate\Support\Facades\Artisan::call('tributario:importar', [
                                    '--tenant' => $record->slug,
                                    '--file' => $mockPath,
                                ]);
                                $output = \Illuminate\Support\Facades\Artisan::output();

                                preg_match('/Atualizados: \d+/u', $output, $ok);
                                preg_match('/Não encontrados.*: \d+/u', $output, $nf);

                                $mensagens[] = $exitCode === 0
                                    ? 'Importação: '.($ok[0] ?? 'concluída').(isset($nf[0]) ? " · {$nf[0]}" : '')
                                    : 'Importação FALHOU — veja os logs.';
                            }

                            $falhou = (bool) collect($mensagens)->first(fn ($m) => str_contains($m, 'FALHOU'));

                            $notificacao = Notification::make()->title('Simulação Tributária')
                                ->body(implode(' ', $mensagens) ?: 'Nada a fazer — nenhum arquivo novo e importação não solicitada.');

                            $falhou ? $notificacao->danger()->send() : $notificacao->success()->send();
                        }),

                    // --- AÇÃO: SINCRONIZAR TRIBUTÁRIO (TODOS) ---
                    // Varre TODAS as unidades imobiliárias com identificador e sincroniza
                    // cada uma pela integração vigente (simulação ou API real), criando
                    // proprietários (Pessoa) e endereços — comando sigweb:sincronizar-imoveis.
                    Tables\Actions\Action::make('sincronizar_tributario')
                        ->label('Sincronizar Tributário (todos)')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('warning')
                        ->visible(fn () => static::pode('admin_tributario_sincronizar'))
                        ->requiresConfirmation()
                        ->modalHeading(fn (Tenant $record) => "Sincronizar todos os imóveis — {$record->name}")
                        ->modalDescription(function (Tenant $record) {
                            $fonte = \App\Services\ApiTools\IntegraPrefeituraService::rotuloIntegracao($record->id)
                                ?? 'nenhuma fonte configurada';
                            $total = \App\Models\UnidadeImobiliaria::withoutGlobalScopes()
                                ->where('tenant_id', $record->id)
                                ->whereNull('deleted_at')
                                ->whereNotNull('codigo_imovel_tributario')
                                ->count();

                            return "Busca os dados fiscais de {$total} unidade(s) com código tributário e atualiza inscrição, endereço, proprietário e o JSON do BIC. Fonte: {$fonte}.";
                        })
                        ->modalSubmitActionLabel('Sim, sincronizar todos')
                        ->action(function (Tenant $record) {
                            // Bases grandes: cada unidade é uma busca na fonte
                            set_time_limit(600);

                            \App\Services\Fiscal\MapaFiscalService::limparCache();

                            $exitCode = \Illuminate\Support\Facades\Artisan::call('sigweb:sincronizar-imoveis', [
                                'tenant_id' => $record->id,
                            ]);
                            $output = \Illuminate\Support\Facades\Artisan::output();

                            preg_match('/Concluído! .+/u', $output, $match);

                            if ($exitCode === 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Sincronização tributária concluída')
                                    ->body($match[0] ?? trim($output))
                                    ->send();
                            } else {
                                Notification::make()
                                    ->danger()
                                    ->title('Sincronização falhou')
                                    ->body(trim($output) ?: 'Verifique os logs do sistema.')
                                    ->send();
                            }
                        }),

                    // --- AÇÃO: RECALCULAR ÁREAS/EXTENSÕES VIA POSTGIS ---
                    // Complemento do importador GIS: preenche area_geo/extensao_geo
                    // chamando o comando gis:recalcular-metadata para o tenant da linha.
                    Tables\Actions\Action::make('recalcular_gis')
                        ->label('Recalcular Áreas (GIS)')
                        ->icon('heroicon-o-calculator')
                        ->color('info')
                        ->visible(fn () => static::pode('admin_recalcular_gis'))
                        ->form([
                            Forms\Components\Select::make('entidade')
                                ->label('Entidade')
                                ->placeholder('Todas as entidades')
                                ->helperText('Deixe em branco para recalcular todas.')
                                ->options([
                                    'perimetros_urbanos' => 'Perímetros Urbanos',
                                    'zonas' => 'Zonas',
                                    'bairros' => 'Bairros',
                                    'loteamentos' => 'Loteamentos',
                                    'quadras' => 'Quadras',
                                    'lotes' => 'Lotes',
                                    'logradouros' => 'Logradouros',
                                    'secoes_logradouro' => 'Seções de Logradouro',
                                    'meio_fios' => 'Meio-fios',
                                ]),
                            Forms\Components\Toggle::make('force')
                                ->label('Sobrescrever valores existentes')
                                ->helperText('Desligado: calcula só registros com área/extensão vazia (seguro após importação). Ligado: recalcula tudo a partir da geometria atual.')
                                ->default(false),
                        ])
                        ->modalHeading('Recalcular áreas e extensões via PostGIS')
                        ->modalDescription('Calcula area_geo (polígonos) e extensao_geo (linhas) a partir da geometria dos registros desta prefeitura.')
                        ->modalSubmitActionLabel('Recalcular')
                        ->action(function (Tenant $record, array $data) {
                            $params = ['--tenant' => $record->slug];

                            if (! empty($data['entidade'])) {
                                $params['--entidade'] = $data['entidade'];
                            }
                            if (! empty($data['force'])) {
                                $params['--force'] = true;
                            }

                            $exitCode = \Illuminate\Support\Facades\Artisan::call('gis:recalcular-metadata', $params);
                            $output = \Illuminate\Support\Facades\Artisan::output();

                            // Mostra só o resumo na notificação (o detalhe por tabela fica no output do comando)
                            preg_match('/Total atualizado: .+/u', $output, $match);

                            if ($exitCode === 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Recálculo concluído!')
                                    ->body($match[0] ?? trim($output))
                                    ->send();
                            } else {
                                Notification::make()
                                    ->danger()
                                    ->title('Falha no recálculo')
                                    ->body(trim($output) ?: 'Verifique os logs do sistema.')
                                    ->send();
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
