<?php

namespace App\Filament\Cidadao\Pages;

use Filament\Pages\Page;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Actions\Action;
use App\Models\Zona;
use App\Models\User;
use App\Models\Lote;
use App\Models\UnidadeImobiliaria;
use Livewire\Attributes\On;
use App\Filament\Cidadao\Pages\Traits\HasFichaImovelPublico;

class MapaPublico extends Page
{
    use HasFichaImovelPublico;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Mapa Público';
    protected static ?string $title = 'Mapa Interativo do Cidadão';
    protected static ?string $slug = 'mapa-publico';

    protected static string $layout = 'filament-panels::components.layout.base';
    protected static string $view = 'filament.cidadao.pages.mapa-publico';

    public float $mapLat = -26.9658952;
    public float $mapLon = -50.4182571;
    public int $mapZoom = 14;
    public int $tenantId = 0;
    public string $tenantSlug = '';

    public array $zonasTipos = [];
    public bool $filtroAvancadoAtivo = false;

    public ?int $pontoPanoramicoAtivoId = null;

    // ---- PROPRIEDADES DA FICHA DO IMÓVEL ----
    public bool $isFichaLoteOpen = false;
    public ?int $activeLoteId = null;
    public ?string $activeLoteNome = null;
    public ?Lote $loteAtivo = null;
    public $loteUnidades = [];

    public function mount()
    {
        $tenant = Filament::getTenant();

        if (!$tenant) {
            /** @var \App\Models\User|null $user */
            $user = Filament::auth()->user();
            if ($user) $tenant = $user->tenants()->first();
        }

        if ($tenant) {
            $this->tenantId = $tenant->id;
            $this->tenantSlug = $tenant->slug ?? (string) $tenant->id;
            $this->mapLat = (float) data_get($tenant->data, 'map_lat', -26.9658952);
            $this->mapLon = (float) data_get($tenant->data, 'map_lon', -50.4182571);
            $this->mapZoom = (int) data_get($tenant->data, 'map_zoom', 14);

            $this->zonasTipos = Zona::where('tenant_id', $this->tenantId)
                ->select('id', 'name', 'sigla', 'rgb')
                ->get()
                ->map(fn($zona) => ['id' => $zona->id, 'name' => $zona->name, 'sigla' => $zona->sigla, 'rgb' => $zona->rgb])
                ->toArray();
        } else {
            \Filament\Notifications\Notification::make()->title('Sem cidade vinculada')->body('Seu usuário não está atrelado a nenhuma prefeitura.')->danger()->send();
        }
    }

    // ---- LÓGICA DO FILTRO AVANÇADO ----
    public function filtroAvancadoAction(): Action
    {
        return Action::make('filtroAvancado')
            ->label('Filtro Avançado')
            ->icon('heroicon-o-funnel')
            ->modalHeading('Construtor de Consultas')
            ->modalDescription('Filtre artefatos por atributos, cruzamentos geográficos ou tematize por classes.')
            ->modalSubmitActionLabel('Iniciar Consulta')
            ->modalWidth('md')
            ->form([
                Forms\Components\Radio::make('tipo_filtro')
                    ->label('Tipo de Consulta')
                    ->options([
                        'atributo'  => 'Por Atributo (Texto / Número)',
                        'espacial'  => 'Cruzamento Espacial (Ex: Lotes dentro de Bairro)',
                        'desenho'   => 'Desenhar Área no Mapa (Polígono / Retângulo)',
                        'intervalo' => 'Tematização por Intervalo (Classes)',
                    ])
                    ->default('atributo')
                    ->live()
                    ->required(),

                // BLOCO 1: FILTRO POR ATRIBUTO
                Forms\Components\Group::make([
                    Forms\Components\Select::make('layer')
                        ->label('Camada / Entidade')
                        ->options([
                            'lotes'              => 'Lotes Urbanos',
                            'edificacoes'        => 'Edificações',
                            'logradouros'        => 'Logradouros',
                            'quadras'            => 'Quadras',
                            'bairros'            => 'Bairros',
                            'rural_propriedades' => 'Propriedades Rurais (CAR)',
                            'rural_estradas'     => 'Estradas Rurais',
                            'rural_pontes'       => 'Pontes Rurais',
                        ])
                        ->live()
                        ->required(fn(Forms\Get $get) => $get('tipo_filtro') === 'atributo'),

                    Forms\Components\Select::make('field')
                        ->label('Atributo (Campo de Busca)')
                        ->options(fn(Forms\Get $get): array => match ($get('layer')) {
                            'lotes'              => ['area_geo' => 'Área em m²', 'main_facade_length' => 'Testada (m)', 'numero_lote' => 'Número do Lote'],
                            'edificacoes'        => ['area_geo' => 'Área Construída (m²)', 'tipo' => 'Tipo de Uso'],
                            'rural_propriedades' => ['area_geo' => 'Área em m²', 'codigo_car' => 'Código CAR'],
                            'rural_estradas'     => ['extensao_geo' => 'Extensão (m)', 'tipo_pavimento' => 'Pavimento', 'condicao_trafego' => 'Condição'],
                            'rural_pontes'       => ['capacidade_carga_toneladas' => 'Capacidade (Ton)', 'material_construcao' => 'Material'],
                            default              => ['name' => 'Nome / Número'],
                        })
                        ->required(fn(Forms\Get $get) => $get('tipo_filtro') === 'atributo'),

                    Forms\Components\Select::make('operator')
                        ->label('Condição (Operador)')
                        ->options([
                            '='    => 'Igual a (=)',
                            '>'    => 'Maior que (>)',
                            '<'    => 'Menor que (<)',
                            '>='   => 'Maior ou igual (>=)',
                            '<='   => 'Menor ou igual (<=)',
                            'LIKE' => 'Contém o texto (LIKE)',
                            '!='   => 'Diferente de (!=)',
                        ])
                        ->default('=')
                        ->required(fn(Forms\Get $get) => $get('tipo_filtro') === 'atributo'),

                    Forms\Components\TextInput::make('value')
                        ->label('Valor da Condição')
                        ->placeholder('Ex: 250, Asfalto, Boa...')
                        ->required(fn(Forms\Get $get) => $get('tipo_filtro') === 'atributo'),

                    Forms\Components\ColorPicker::make('cor_tematizacao')
                        ->label('Cor da Tematização')
                        ->default('#f59e0b')
                        ->helperText('Escolha a cor para destacar os resultados no mapa.')
                        ->required(),

                ])->visible(fn(Forms\Get $get) => $get('tipo_filtro') === 'atributo'),

                // BLOCO 2: CRUZAMENTO ESPACIAL
                Forms\Components\Group::make([
                    Forms\Components\Select::make('spatial_target_layer')
                        ->label('O que você quer encontrar? (Alvo)')
                        ->options([
                            'logradouros'        => 'Logradouros',
                            'lotes'              => 'Lotes Urbanos',
                            'edificacoes'        => 'Edificações',
                            'postes'             => 'Postes / Iluminação',
                            'arvores'            => 'Arborização',
                            'rural_propriedades' => 'Propriedades Rurais (CAR)',
                        ])
                        ->required(fn(Forms\Get $get) => $get('tipo_filtro') === 'espacial'),

                    Forms\Components\Select::make('spatial_operator')
                        ->label('Qual a relação topológica?')
                        ->options([
                            'ST_Within'     => 'Que estejam DENTRO de',
                            'ST_Intersects' => 'Que tocam / Cruzam com',
                        ])
                        ->default('ST_Intersects')
                        ->required(fn(Forms\Get $get) => $get('tipo_filtro') === 'espacial'),

                    Forms\Components\Select::make('spatial_reference_layer')
                        ->label('Qual a área de referência?')
                        ->options([
                            'quadras'          => 'Quadras',
                            'bairros'          => 'Bairros',
                            'loteamentos'      => 'Loteamentos',
                            'zonas'            => 'Zonas Urbanas',
                            'rural_localidades' => 'Localidades Rurais / Distritos',
                            'cemiterios'       => 'Cemitérios',
                        ])
                        ->live()
                        ->required(fn(Forms\Get $get) => $get('tipo_filtro') === 'espacial'),

                    Forms\Components\Select::make('spatial_reference_id')
                        ->label('Escolha o local específico')
                        ->options(function (Forms\Get $get) {
                            $refLayer = $get('spatial_reference_layer');
                            if (!$refLayer) return [];
                            return match ($refLayer) {
                                'quadras'           => \App\Models\Quadra::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id')->toArray(),
                                'bairros'           => \App\Models\Bairro::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id')->toArray(),
                                'loteamentos'       => \App\Models\Loteamento::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id')->toArray(),
                                'zonas'             => \App\Models\Zona::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id')->toArray(),
                                'rural_localidades' => \App\Models\RuralLocalidade::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id')->toArray(),
                                'cemiterios'        => \App\Models\Cemiterio::query()->where('tenant_id', $this->tenantId)->pluck('name', 'id')->toArray(),
                                default             => [],
                            };
                        })
                        ->searchable()
                        ->multiple()
                        ->required(fn(Forms\Get $get) => $get('tipo_filtro') === 'espacial'),

                    Forms\Components\ColorPicker::make('cor_tematizacao')
                        ->label('Cor da Tematização')
                        ->default('#f59e0b')
                        ->helperText('Escolha a cor para destacar os resultados no mapa.')
                        ->required(),

                ])->visible(fn(Forms\Get $get) => $get('tipo_filtro') === 'espacial'),

                // BLOCO 3: DESENHO LIVRE
                Forms\Components\Group::make([
                    Forms\Components\Select::make('draw_target_layer')
                        ->label('O que você quer encontrar dentro da área?')
                        ->options([
                            'logradouros'        => 'Logradouros',
                            'bairros'            => 'Bairros',
                            'lotes'              => 'Lotes Urbanos',
                            'edificacoes'        => 'Edificações',
                            'postes'             => 'Postes / Iluminação',
                            'arvores'            => 'Arborização',
                            'rural_propriedades' => 'Propriedades Rurais (CAR)',
                        ])
                        ->required(fn(Forms\Get $get) => $get('tipo_filtro') === 'desenho'),

                    Forms\Components\Toggle::make('draw_within')
                        ->label('Mostrar apenas o que está TOTALMENTE dentro do desenho')
                        ->helperText('Se desativado, mostrará o que cruza ou toca a linha.')
                        ->default(false),

                    Forms\Components\Select::make('draw_shape')
                        ->label('Formato do Desenho')
                        ->options([
                            'Polygon' => 'Traçado Livre (Polígono)',
                            'Box'     => 'Caixa (Retângulo)',
                        ])
                        ->default('Polygon')
                        ->required(fn(Forms\Get $get) => $get('tipo_filtro') === 'desenho'),

                    Forms\Components\ColorPicker::make('cor_tematizacao')
                        ->label('Cor da Tematização')
                        ->default('#f59e0b')
                        ->helperText('Escolha a cor para destacar os resultados no mapa.')
                        ->required(),

                ])->visible(fn(Forms\Get $get) => $get('tipo_filtro') === 'desenho'),

                // BLOCO 4: INTERVALO DE CLASSES (CHOROPLETH)
                Forms\Components\Group::make([
                    Forms\Components\Select::make('interval_layer')
                        ->label('Camada para Análise')
                        ->options(['lotes' => 'Lotes Urbanos', 'edificacoes' => 'Edificações'])
                        ->default('lotes'),

                    Forms\Components\Select::make('interval_attribute')
                        ->label('Atributo Numérico (Escala)')
                        ->options([
                            'area_geo'           => 'Área em m²',
                            'main_facade_length' => 'Testada (m)',
                        ])
                        ->default('area_geo'),

                    Forms\Components\Select::make('num_classes')
                        ->label('Quantidade de Intervalos')
                        ->options(['3' => '3 Faixas', '5' => '5 Faixas', '7' => '7 Faixas'])
                        ->default('5'),

                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\ColorPicker::make('cor_inicio')
                            ->label('Cor Inicial (menor valor)')
                            ->default('#ffffb2'),
                        Forms\Components\ColorPicker::make('cor_fim')
                            ->label('Cor Final (maior valor)')
                            ->default('#800026'),
                    ]),
                ])->visible(fn(Forms\Get $get) => $get('tipo_filtro') === 'intervalo'),

            ])
            ->action(function (array $data) {
                $this->filtroAvancadoAtivo = true;

                if ($data['tipo_filtro'] === 'intervalo') {
                    $this->dispatch('executar-tematizacao-intervalo', dados: $data);
                    \Filament\Notifications\Notification::make()->title('Calculando Densidades...')->info()->send();
                } elseif ($data['tipo_filtro'] === 'desenho') {
                    $this->dispatch('iniciar-desenho-filtro', dados: $data);
                    \Filament\Notifications\Notification::make()
                        ->title('Modo de Desenho Ativo')
                        ->body('Clique no mapa para desenhar a área. Dê dois cliques para finalizar.')
                        ->info()->send();
                } else {
                    $this->dispatch('executar-filtro-avancado', dados: $data);
                    \Filament\Notifications\Notification::make()->title('Analisando base de dados...')->info()->send();
                }
            });
    }

    public function limparFiltroAvancado()
    {
        $this->filtroAvancadoAtivo = false;
        $this->dispatch('limpar-filtro-avancado');
        \Filament\Notifications\Notification::make()->title('Filtros removidos!')->success()->send();
    }

    // ------------------------------------------------------------------------
    // MÉTODOS DO VISUALIZADOR 360º PÚBLICO
    // ------------------------------------------------------------------------

    #[On('abrirVisualizadorPublico360')]
    public function abrirVisualizadorPublico360($id)
    {
        $this->pontoPanoramicoAtivoId = $id;
        $this->mountAction('visualizador360Action');
    }

    public function visualizador360Action(): Action
    {
        return Action::make('visualizador360Action')
            ->modalHeading('Explorar Imagem 360º')
            ->modalSubmitAction(false) // Esconde o botão "Salvar" pois é apenas leitura
            ->modalCancelActionLabel('Fechar')
            ->modalWidth('5xl') // Largura máxima para dar imersão ao Cidadão
            ->modalContent(function () {
                $ponto = \App\Models\PontoPanoramico::find($this->pontoPanoramicoAtivoId);

                // Lógica idêntica à sua Trait: Pega a foto ou usa a de simulação
                $imagemUrl = ($ponto && $ponto->image_path)
                    ? asset('storage/' . $ponto->image_path)
                    : 'https://pannellum.org/images/alma.jpg';

                $uniqueId = 'pano_' . uniqid();

                return view('filament.cidadao.components.visualizador-360-publico', [
                    'ponto' => $ponto,
                    'imagemUrl' => $imagemUrl,
                    'uniqueId' => $uniqueId,
                ]);
            });
    }
}
