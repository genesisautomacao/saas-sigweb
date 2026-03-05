<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Facades\Filament;
use Livewire\Attributes\On;
use Filament\Notifications\Notification;

class MapaFullscreen extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Mapa Interativo';
    protected static ?string $title = 'SIGWEB - Mapa Interativo';
    protected static ?string $slug = 'mapa-interativo';

    // O SEGREDO DO FULLSCREEN: Carrega a página sem Sidebar e sem Navbar!
    protected static string $layout = 'filament-panels::components.layout.base';

    protected static string $view = 'filament.pages.mapa-fullscreen';

    public float $mapLat = -26.9658952;
    public float $mapLon = -50.4182571;
    public int $mapZoom = 14;
    public int $tenantId = 0;
    public string $tenantSlug = '';
    public ?int $loteAtivoId = null;
    public ?string $loteAtivoNome = null;
    public bool $showFicha = false;
    public array $zonasTipos = [];
    public float $loteAreaGeo = 0.0;
    public float $loteAreaConstruida = 0.0;
    public float $loteFacePrincipal = 0.0;
    public array $unidadesImobiliarias = [];
    public bool $showModalUnidades = false;
    public ?array $geometriaRascunho = null;
    public ?int $quadraRascunhoId = null;
    public ?int $zonaRascunhoId = null;

    public function mount()
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            $this->tenantId = $tenant->id;
            // Pegamos o Slug correto para o botão "Voltar" não quebrar!
            $this->tenantSlug = $tenant->slug ?? (string) $tenant->id;

            $this->mapLat = (float) data_get($tenant->data, 'map_lat', -26.9658952);
            $this->mapLon = (float) data_get($tenant->data, 'map_lon', -50.4182571);
            $this->mapZoom = (int) data_get($tenant->data, 'map_zoom', 14);

            $this->zonasTipos = \App\Models\Zona::where('tenant_id', $this->tenantId)
                ->select('id', 'name', 'sigla', 'rgb')
                ->distinct()
                ->get()
                // O MAP extrai apenas os campos limpos, evitando que o toArray() quebre a Model
                ->map(fn($zona) => [
                    'id' => $zona->id,
                    'name' => $zona->name,
                    'sigla' => $zona->sigla,
                    'rgb' => $zona->rgb,
                ])
                ->toArray();
        }
    }

    // Atualize o método que carrega a Ficha:
    #[On('abrirFichaImovel')]
    public function carregarFicha($loteId, $loteNome = 'S/N')
    {
        $this->loteAtivoId = $loteId;
        $this->loteAtivoNome = $loteNome;

        // 1. Busca a Área do Lote e Face Principal
        $lote = \App\Models\Lote::find($loteId);
        $this->loteAreaGeo = $lote ? (float) $lote->area_geo : 0.0;
        $this->loteFacePrincipal = $lote ? (float) $lote->main_facade_length : 0.0;

        // 2. Busca a soma das áreas das Edificações vinculadas a este lote
        $this->loteAreaConstruida = (float) \App\Models\Edificacao::where('lote_id', $loteId)->sum('area_geo');

        $this->showFicha = true;
    }

    public function fecharFicha()
    {
        $this->showFicha = false;
        $this->loteAtivoId = null;
    }

    public function abrirModalUnidades()
    {
        if ($this->loteAtivoId) {

            $this->unidadesImobiliarias = \App\Models\UnidadeImobiliaria::where('lote_id', $this->loteAtivoId)
                ->select('id', 'codigo_imovel_tributario', 'inscricao_imobiliaria')
                ->get()
                // Usamos o MAP para extrair os dados puros, evitando o erro da coluna 'geo' no toArray()
                ->map(fn($unidade) => [
                    'id' => $unidade->id,
                    'codigo_imovel_tributario' => $unidade->codigo_imovel_tributario,
                    'inscricao_imobiliaria' => $unidade->inscricao_imobiliaria,
                ])
                ->toArray();
        }
        $this->showModalUnidades = true;
    }

    public function fecharModalUnidades()
    {
        $this->showModalUnidades = false;
    }

    public function mostrarErro(string $mensagem): void
    {
        Notification::make()
            ->title('Atenção')
            ->body($mensagem)
            ->warning() // Cria um alerta amarelo bonito do Filament
            ->send();
    }


    // --- INTERCEPTADOR COM INTELIGÊNCIA ESPACIAL ---
    #[On('abrirModalCriacao')]
    public function interceptarDesenho($entityType, $geoJson)
    {
        $this->geometriaRascunho = $geoJson;

        // Converte o GeoJSON para WKT para o PostGIS conseguir ler
        $polyWKT = "ST_GeomFromGeoJSON('" . json_encode($geoJson) . "')";
        $centroidWKT = "ST_Centroid($polyWKT)";

        // 🛡️ REGRAS ESPECÍFICAS DO LOTE
        if ($entityType === 'lote') {

            // 🚨 TRAVA 1: TOPOLOGIA (BLINDADA CONTRA MICROMILÍMETROS)
            $sobreposicao = \App\Models\Lote::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Intersects(geo, $polyWKT)")
                // MÁGICA: Calcula a área (em metros quadrados) em que um lote invade o outro. 
                // Tolera até 0.1 m² de "escorregão" do mouse na hora do clique/snap.
                ->whereRaw("ST_Area(ST_Intersection(geo, $polyWKT)::geography) > 0.1") 
                ->exists();

            if ($sobreposicao) {
                \Filament\Notifications\Notification::make()
                    ->title('Conflito Espacial')
                    ->body('O Lote não pode sobrepor, cruzar ou estar dentro de um Lote já existente no mapa.')
                    ->danger()
                    ->send();
                    
                $this->dispatch('limpar-rascunho-mapa'); 
                return;
            }

            // 🚨 TRAVA 2: Deve estar dentro de uma Quadra
            $quadra = \App\Models\Quadra::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Intersects(geo, $centroidWKT)")
                ->first();

            if (!$quadra) {
                \Filament\Notifications\Notification::make()
                    ->title('Operação Cancelada')
                    ->body('O Lote deve ser desenhado DENTRO de uma Quadra existente.')
                    ->danger()
                    ->send();
                    
                $this->dispatch('limpar-rascunho-mapa'); 
                return;
            }

            // 2. Busca a Zona (se houver)
            $zona = \App\Models\Zona::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Intersects(geo, $centroidWKT)")
                ->first();

            $this->quadraRascunhoId = $quadra->id;
            $this->zonaRascunhoId = $zona ? $zona->id : null;
        }

        $actionName = 'criar' . ucfirst($entityType);

        try {
            $this->mountAction($actionName);
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Erro de Sistema')
                ->body("A ação '{$actionName}' ainda não foi programada no backend!")
                ->danger()
                ->send();
        }
    }

    // --- A MODAL NATIVA DO FILAMENT (CRIAR LOTE) ---
    public function criarLoteAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('criarLote')
            ->modalHeading('Cadastrar Novo Lote')
            ->modalDescription('Preencha os dados básicos. A quadra e a zona já foram detectadas pelo satélite.')
            ->modalSubmitActionLabel('Salvar Lote e Unidade')
            ->modalWidth('md')
            ->fillForm(function (): array {
                return [
                    'quadra_id' => $this->quadraRascunhoId,
                ];
            })
            ->form([
                \Filament\Forms\Components\TextInput::make('numero_lote')
                    ->label('Número do Lote')
                    ->required()
                    ->maxLength(255)
                    // 🛡️ TRAVA DE DUPLICIDADE: Único por Quadra e Prefeitura
                    ->unique(
                        table: 'lotes',
                        column: 'numero_lote',
                        modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule) {
                            return $rule
                                ->where('tenant_id', $this->tenantId)
                                ->where('quadra_id', $this->quadraRascunhoId);
                        }
                    )
                    ->validationMessages([
                        'unique' => 'Já existe um Lote com este número nesta exata Quadra.',
                    ]),

                \Filament\Forms\Components\TextInput::make('main_facade_length')
                    ->label('Testada Principal / Frente (metros)')
                    ->numeric()
                    ->nullable(),

                \Filament\Forms\Components\Select::make('quadra_id')
                    ->label('Quadra (Auto-detectada)')
                    ->options(function () {
                        return \App\Models\Quadra::where('tenant_id', $this->tenantId)->pluck('name', 'id');
                    })
                    ->disabled()
                    ->dehydrated()
                    ->required(),

                // NOVO: Campo para o Código Tributário da Unidade
                \Filament\Forms\Components\TextInput::make('codigo_imovel_tributario')
                    ->label('Código do Imóvel Tributário (Opcional)')
                    ->maxLength(255),

                \Filament\Forms\Components\TextInput::make('inscricao_imobiliaria')
                    ->label('Inscrição Imobiliária Base (Opcional)')
                    ->maxLength(255),
            ])
            ->action(function (array $data) {
                // 1. DADOS DO LOTE
                $data['tenant_id'] = $this->tenantId;
                $data['geo'] = $this->geometriaRascunho;
                $data['zona_id'] = $this->zonaRascunhoId;
                $data['code'] = (string) \Illuminate\Support\Str::uuid();

                $lote = \App\Models\Lote::create($data);

                // Calcula a área real da Terra
                \Illuminate\Support\Facades\DB::statement("
                    UPDATE lotes 
                    SET area_geo = ST_Area(geo::geography) 
                    WHERE id = ?
                ", [$lote->id]);

                // 2. CRIAÇÃO AUTOMÁTICA DA UNIDADE IMOBILIÁRIA (AGORA PEGANDO OS DADOS!)
                if ($lote) {
                    $unidade = \App\Models\UnidadeImobiliaria::create([
                        'tenant_id' => $lote->tenant_id,
                        'code' => (string) \Illuminate\Support\Str::uuid(),
                        'lote_id' => $lote->id,
                        // ✅ CORREÇÃO: Pega o que foi digitado no array $data
                        'codigo_imovel_tributario' => $data['codigo_imovel_tributario'] ?? null,
                        'inscricao_imobiliaria' => $data['inscricao_imobiliaria'] ?? null,
                    ]);

                    if ($unidade) {
                        \Illuminate\Support\Facades\DB::statement("
                            UPDATE unidade_imobiliarias
                            SET geo = (SELECT ST_PointOnSurface(geo) FROM lotes WHERE id = ?)
                            WHERE id = ?
                        ", [$lote->id, $unidade->id]);
                    }
                }

                \Filament\Notifications\Notification::make()
                    ->title('Sucesso!')
                    ->body('Lote e Unidade Imobiliária cadastrados.')
                    ->success()
                    ->send();

                // Injeta o Lote na tela
                $this->dispatch('adicionar-lote-mapa', [
                    'id' => $lote->id,
                    'numero_lote' => $lote->numero_lote,
                    'geo' => $this->geometriaRascunho
                ]);

                $this->geometriaRascunho = null;
            });
    }

    // --- AÇÃO PADRÃO: EXCLUIR ARTEFATO ---
    public function deletarArtefatoAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('deletarArtefato')
            ->label('Excluir Artefato')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Excluir Artefato Definitivamente')
            ->modalDescription('Tem certeza que deseja excluir este artefato do mapa? Esta ação não pode ser desfeita e excluirá os itens vinculados a ele (como unidades imobiliárias).')
            ->modalSubmitActionLabel('Sim, excluir')
            ->action(function () {
                // Como ainda estamos focados no Lote, apagamos ele.
                // No futuro, usaremos o $entityType para saber de qual tabela apagar.
                if ($this->loteAtivoId) {
                    $lote = \App\Models\Lote::where('id', $this->loteAtivoId)
                        ->where('tenant_id', $this->tenantId)
                        ->first();

                    if ($lote) {
                        // 1. Apaga as Unidades Imobiliárias "filhas" primeiro para não deixar sujeira no banco
                        \App\Models\UnidadeImobiliaria::where('lote_id', $lote->id)->delete();
                        
                        // 2. Apaga as Edificações atreladas a este lote (opcional, mas recomendado)
                        \App\Models\Edificacao::where('lote_id', $lote->id)->delete();
                        
                        // 3. Agora sim, apaga o Lote com segurança
                        $lote->delete();

                        \Filament\Notifications\Notification::make()
                            ->title('Excluído!')
                            ->body('O artefato foi removido com sucesso.')
                            ->success()
                            ->send();

                        // Pede para o JS apagar o polígono da tela
                        $this->dispatch('remover-lote-mapa', ['id' => $this->loteAtivoId]);

                        // Fecha a aba lateral
                        $this->fecharFicha();
                    }
                }
            });
    }

}