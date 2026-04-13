<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Actions\Action;

use App\Filament\Pages\Traits\HasLoteActions;
use App\Filament\Pages\Traits\HasEdificacaoActions;
use \App\Filament\Pages\Traits\HasLogradouroActions;
use App\Filament\Pages\Traits\HasPosteActions;
use App\Filament\Pages\Traits\HasArvoreActions;
use App\Filament\Pages\Traits\HasCemiterioActions;
use App\Filament\Pages\Traits\HasQuadraCemiterioActions;
use App\Filament\Pages\Traits\HasLogradouroCemiterioActions;
use App\Filament\Pages\Traits\HasJazigoActions;
use \App\Filament\Pages\Traits\HasSetorFiscalActions;
use App\Filament\Pages\Traits\HasRuralLocalidadeActions;
use App\Filament\Pages\Traits\HasRuralPropriedadeActions;
use App\Filament\Pages\Traits\HasRuralEstradaActions;
use App\Filament\Pages\Traits\HasRuralHidrografiaActions;
use App\Filament\Pages\Traits\HasRuralPonteActions;
use App\Filament\Pages\Traits\HasRuralPontoInteresseActions;
use App\Filament\Pages\Traits\HasBairroActions;
use App\Filament\Pages\Traits\HasLoteamentoActions;
use App\Filament\Pages\Traits\HasQuadraActions;
use App\Filament\Pages\Traits\HasZonaActions;
use App\Filament\Pages\Traits\HasPontoPanoramicoActions;

use App\Models\Lote;
use App\Models\Edificacao;
use App\Models\Zona;
use App\Models\Quadra;
use App\Models\Poste;
use App\Models\Arvore;
use App\Models\Cemiterio;
use App\Models\QuadraCemiterio;
use App\Models\LogradouroCemiterio;
use App\Models\Jazigo;
use Filament\Pages\Page;
use Filament\Facades\Filament;
use Livewire\Attributes\On;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;


class MapaFullscreen extends Page
{
    // Injetando as gavetas de lógica (Traits)
    use HasLoteActions;
    use HasEdificacaoActions;
    use HasLogradouroActions;
    use HasPosteActions;
    use HasArvoreActions;
    use HasCemiterioActions;
    use HasQuadraCemiterioActions;
    use HasLogradouroCemiterioActions;
    use HasJazigoActions;
    use HasSetorFiscalActions;
    use HasRuralLocalidadeActions;
    use HasRuralPropriedadeActions;
    use HasRuralEstradaActions;
    use HasRuralHidrografiaActions;
    use HasRuralPonteActions;
    use HasRuralPontoInteresseActions;
    use HasRuralEstradaActions;
    use HasRuralPonteActions;
    use HasRuralPontoInteresseActions;
    use HasBairroActions;
    use HasLoteamentoActions;
    use HasQuadraActions;
    use HasZonaActions;
    use HasPontoPanoramicoActions;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Mapa Interativo';
    protected static ?string $title = 'SIGWEB - Mapa Interativo';
    protected static ?string $slug = 'mapa-interativo';

    protected static string $layout = 'filament-panels::components.layout.base';
    protected static string $view = 'filament.pages.mapa-fullscreen';

    // Propriedades de Estado do Mapa
    public float $mapLat = -26.9658952;
    public float $mapLon = -50.4182571;
    public int $mapZoom = 14;
    public int $tenantId = 0;
    public string $tenantSlug = '';

    // Propriedades do Lote Ativo
    public ?int $loteAtivoId = null;
    public ?string $loteAtivoNome = null;
    public ?string $loteSequentialId = null;
    public bool $showFicha = false;
    public float $loteAreaGeo = 0.0;
    public float $loteAreaConstruida = 0.0;
    public float $loteFacePrincipal = 0.0;

    /* desmembramento */
    public ?int $loteParaDesmembrarId = null;
    public ?string $linhaDeCorteGeoJson = null;
    public array $previewDesmembramento = [];

    /* unificação */
    public ?int $lotePrincipalToUnifyId = null;
    public ?int $loteSecundarioToUnifyId = null;
    public array $previewUnificacao = [];

    // Propriedades de Rascunho e Edificação
    public ?array $geometriaRascunho = null;
    public ?int $quadraRascunhoId = null;
    public ?int $zonaRascunhoId = null;
    public bool $mostrarEdificacoesLoteAtivo = false;
    public ?int $edificacaoAtivaId = null;
    public array $zonasTipos = [];
    public ?int $logradouroAtivoId = null;
    public ?int $cemiterioAtivoId = null;
    public ?int $jazigoAtivoId = null;

    // Propriedades da Numeração Automática
    public ?int $numLogradouroId = null;
    public ?string $numLogradouroNome = null;
    public ?string $numDrawnLine = null;
    public bool $previewNumeracaoAtivo = false;
    public array $resultadosNumeracao = [];

    /* altimetria */
    public array $altimetriaData = [];

    // Propriedades do PGV
    public ?int $setorFiscalAtivoId = null;
    public bool $previewPgvAtivo = false;
    public array $resultadosPgv = [];

    //Rural
    public ?int $ruralLocalidadePreSelecionadaId = null;

    // Filtro
    public bool $filtroAvancadoAtivo = false;

    public function mount()
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            $this->tenantId = $tenant->id;
            $this->tenantSlug = $tenant->slug ?? (string) $tenant->id;
            $this->mapLat = (float) data_get($tenant->data, 'map_lat', -26.9658952);
            $this->mapLon = (float) data_get($tenant->data, 'map_lon', -50.4182571);
            $this->mapZoom = (int) data_get($tenant->data, 'map_zoom', 14);

            $this->zonasTipos = Zona::where('tenant_id', $this->tenantId)
                ->select('id', 'name', 'sigla', 'rgb')
                ->get()
                ->map(fn($zona) => [
                    'id' => $zona->id,
                    'name' => $zona->name,
                    'sigla' => $zona->sigla,
                    'rgb' => $zona->rgb,
                ])
                ->toArray();
        }
    }

    #[On('abrirFichaImovel')]
    public function carregarFicha($loteId, $loteNome = 'S/N')
    {
        if ($this->loteAtivoId !== null && $this->loteAtivoId != $loteId) {
            $this->mostrarEdificacoesLoteAtivo = false;
            $this->dispatch('esconder-edificacoes-lote');
        }

        $this->loteAtivoId = $loteId;
        $this->loteAtivoNome = $loteNome;

        $lote = Lote::find($loteId);
        $this->loteAreaGeo = $lote ? (float) $lote->area_geo : 0.0;
        $this->loteFacePrincipal = $lote ? (float) $lote->main_facade_length : 0.0;
        $this->loteAreaConstruida = (float) Edificacao::where('lote_id', $loteId)->sum('area_geo');
        $this->loteSequentialId = $lote ? $lote->sequential_id : 'S/N';

        $this->showFicha = true;
    }

    public function fecharFicha()
    {
        $this->showFicha = false;
    }

    #[On('abrirModalCriacao')]
    public function interceptarDesenho($entityType, $geoJson)
    {
        if (is_array($geoJson) && isset($geoJson['type']) && $geoJson['type'] === 'Polygon') {
            if (!empty($geoJson['coordinates'][0])) {
                $primeiroPonto = $geoJson['coordinates'][0][0];
                $ultimoPonto = end($geoJson['coordinates'][0]);

                // Se o primeiro ponto for diferente do último, nós injetamos o fechamento
                if ($primeiroPonto !== $ultimoPonto) {
                    $geoJson['coordinates'][0][] = $primeiroPonto;
                }
            }
        }

        $this->geometriaRascunho = $geoJson;

        // 🛡️ BÔNUS: Já envelopamos no ST_MakeValid para evitar erros de "quina torcida" na criação
        $polyWKT = "ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($geoJson) . "'), 4326))";
        $centroidWKT = "ST_Centroid($polyWKT)";

        if ($entityType === 'lote') {
            $sobreposicao = Lote::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Intersects(geo, $polyWKT)")
                ->whereRaw("ST_Area(ST_Intersection(geo, $polyWKT)::geography) > 0.1")
                ->exists();

            if ($sobreposicao) {
                Notification::make()->title('Conflito Espacial')->danger()->send();
                $this->dispatch('limpar-rascunho-mapa');
                return;
            }

            $quadra = Quadra::where('tenant_id', $this->tenantId)->whereRaw("ST_Intersects(geo, $centroidWKT)")->first();
            if (!$quadra) {
                Notification::make()->title('Fora de Quadra')->danger()->send();
                $this->dispatch('limpar-rascunho-mapa');
                return;
            }

            $this->quadraRascunhoId = $quadra->id;
            $this->zonaRascunhoId = Zona::where('tenant_id', $this->tenantId)->whereRaw("ST_Intersects(geo, $centroidWKT)")->value('id');

            // 🛑 MÁGICA: Monta a ação do Lote aqui e encerra
            $this->mountAction('criarLote');
        } elseif ($entityType === 'edificacao') {
            $contido = Lote::where('id', $this->loteAtivoId)
                ->whereRaw("ST_Area(ST_Difference($polyWKT, geo)::geography) <= 0.1")
                ->exists();

            if (!$contido) {
                Notification::make()->title('Erro de Topologia')
                    ->body('A edificação invadiu a rua ou o lote vizinho além do limite permitido.')
                    ->danger()->send();
                $this->dispatch('limpar-rascunho-mapa');
                return;
            }

            // 🛑 MÁGICA: Monta a ação da Edificação aqui e encerra
            $this->mountAction('criarEdificacao');
        } elseif ($entityType === 'logradouro') {
            // 🛑 MÁGICA: Monta a ação do Logradouro aqui e encerra
            $this->mountAction('criarLogradouro');
        } elseif ($entityType === 'poste') {
            // 🛑 MÁGICA: Monta a ação do Poste aqui e encerra
            $this->mountAction('criarPoste');
        } elseif ($entityType === 'arvore') { // <-- NOVO BLOCO
            $this->mountAction('criarArvore');
        } elseif ($entityType === 'cemiterio') { // <-- NOVO BLOCO
            $this->mountAction('criarCemiterio');
        } elseif ($entityType === 'quadra_cemiterio') {

            // 🛑 MÁGICA TOPOLÓGICA: Verifica se o centro do desenho caiu dentro de um Cemitério
            $cemiterio = \App\Models\Cemiterio::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Intersects(geo, $centroidWKT)")
                ->first();

            if (!$cemiterio) {
                \Filament\Notifications\Notification::make()
                    ->title('Fora do Cemitério!')
                    ->body('A quadra deve ser desenhada DENTRO da área de um cemitério já existente.')
                    ->danger()
                    ->send();

                $this->dispatch('limpar-rascunho-mapa');
                return; // Aborta e nem abre a modal!
            }

            // Salva o ID do cemitério detectado para a Trait injetar no formulário
            $this->cemiterioPreSelecionadoId = $cemiterio->id;

            $this->mountAction('criarQuadraCemiterio');
        } elseif ($entityType === 'logradouro_cemiterio') {

            // Verifica se a linha desenhada passa por dentro de algum cemitério
            $cemiterio = \App\Models\Cemiterio::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Intersects(geo, $polyWKT)") // Usa $polyWKT (que no caso da rua é a LineString)
                ->first();

            if (!$cemiterio) {
                \Filament\Notifications\Notification::make()
                    ->title('Fora do Cemitério!')
                    ->body('As ruas internas devem ser desenhadas dentro de um cemitério cadastrado.')
                    ->danger()
                    ->send();

                $this->dispatch('limpar-rascunho-mapa');
                return;
            }

            $this->cemiterioPreSelecionadoId = $cemiterio->id;
            $this->mountAction('criarLogradouroCemiterio');
        } elseif ($entityType === 'jazigo') {

            // 🛑 MÁGICA TOPOLÓGICA: O Jazigo precisa estar dentro de uma Quadra de Cemitério!
            $quadra = \App\Models\QuadraCemiterio::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Intersects(geo, $polyWKT)")
                ->first();

            if (!$quadra) {
                \Filament\Notifications\Notification::make()
                    ->title('Fora da Quadra!')
                    ->body('O Jazigo deve ser desenhado DENTRO dos limites de uma Quadra de Cemitério.')
                    ->danger()
                    ->send();

                $this->dispatch('limpar-rascunho-mapa');
                return;
            }

            // Salva o ID da Quadra detectada
            $this->quadraCemiterioPreSelecionadaId = $quadra->id;
            $this->mountAction('criarJazigo');
        } elseif ($entityType === 'setor_fiscal') {

            // Impede sobreposição (Tolerância de 5m² para o Ímã funcionar sem falsos positivos de borda)
            $sobreposicao = \App\Models\SetorFiscal::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Intersects(geo, $polyWKT)")
                ->whereRaw("ST_Area(ST_Intersection(geo::geometry, $polyWKT)::geography) > 5.0")
                ->exists();

            if ($sobreposicao) {
                \Filament\Notifications\Notification::make()
                    ->title('Sobreposição Detectada')
                    ->body('Um Setor Fiscal não pode invadir a área de outro setor. Use a ferramenta com o ímã ativado para colar as bordas sem cruzar para dentro.')
                    ->danger()
                    ->send();

                $this->dispatch('limpar-rascunho-mapa');
                return;
            }

            $this->mountAction('criarSetorFiscal');
        } elseif ($entityType === 'rural_localidade') {
            // 🛑 Aqui o PHP recebe o desenho do JS e abre a modal da Trait
            $this->mountAction('criarRuralLocalidadeAction');
        } elseif ($entityType === 'rural_propriedade') {

            $polyWKT = "ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($geoJson) . "'), 4326)";

            // 🛑 REGRA 1: Tenta achar uma localidade que cubra 100% da propriedade desenhada (com 1m² de tolerância de borda)
            $localidade = \App\Models\RuralLocalidade::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Area(ST_Difference($polyWKT, geo::geometry)::geography) <= 1.0")
                ->first();

            if (!$localidade) {
                \Filament\Notifications\Notification::make()
                    ->title('Limites Vazados!')
                    ->body('A Propriedade Rural deve ser desenhada inteiramente DENTRO de uma Localidade existente, sem vazar para fora.')
                    ->danger()
                    ->send();

                $this->dispatch('limpar-rascunho-mapa');
                return; // Aborta e nem abre a modal!
            }

            // O PostGIS achou a localidade! Guardamos o ID para injetar no Select da modal.
            $this->ruralLocalidadePreSelecionadaId = $localidade->id;

            $this->mountAction('criarRuralPropriedade');
        } elseif ($entityType === 'rural_estrada') {

            $polyWKT = "ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($geoJson) . "'), 4326)";

            // 🛑 REGRA INTELIGENTE: Pega a localidade onde o primeiro ponto da estrada tocou
            $localidade = \App\Models\RuralLocalidade::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Intersects(geo, ST_StartPoint($polyWKT))")
                ->first();

            // Se o usuário começou a desenhar de fora pra dentro, procura qualquer localidade que a linha corte
            if (!$localidade) {
                $localidade = \App\Models\RuralLocalidade::where('tenant_id', $this->tenantId)
                    ->whereRaw("ST_Intersects(geo, $polyWKT)")
                    ->first();
            }

            if (!$localidade) {
                \Filament\Notifications\Notification::make()
                    ->title('Estrada Desconectada!')
                    ->body('Uma estrada precisa passar ou fazer fronteira com pelo menos uma Localidade rural cadastrada.')
                    ->danger()
                    ->send();

                $this->dispatch('limpar-rascunho-mapa');
                return;
            }

            $this->ruralLocalidadePreSelecionadaId = $localidade->id;
            $this->mountAction('criarRuralEstrada');
        } elseif (str_starts_with($entityType, 'rural_hidro_')) {

            $polyWKT = "ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($geoJson) . "'), 4326)";

            // Intersecta com qualquer localidade (rios e lagos cruzam fronteiras)
            $localidade = \App\Models\RuralLocalidade::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Intersects(geo, $polyWKT)")
                ->first();

            if (!$localidade) {
                \Filament\Notifications\Notification::make()
                    ->title('Fora de Localidade!')
                    ->body('A hidrografia deve estar contida ou cruzar uma Localidade rural cadastrada no sistema.')
                    ->danger()
                    ->send();

                $this->dispatch('limpar-rascunho-mapa');
                return;
            }

            $this->ruralLocalidadePreSelecionadaId = $localidade->id;
            $this->mountAction('criarRuralHidrografia'); // Cai sempre na mesma Action!

        } elseif ($entityType === 'rural_ponte') {

            $polyWKT = "ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($geoJson) . "'), 4326)";

            // REGRA 1: Verifica em qual Localidade o ponto caiu
            $localidade = \App\Models\RuralLocalidade::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Intersects(geo, $polyWKT)")
                ->first();

            if (!$localidade) {
                \Filament\Notifications\Notification::make()
                    ->title('Fora de Localidade!')
                    ->body('A ponte deve ser registrada dentro dos limites de uma Localidade rural cadastrada.')
                    ->danger()
                    ->send();

                $this->dispatch('limpar-rascunho-mapa');
                return;
            }

            // MÁGICA 2: Procura se o clique do mouse ocorreu num raio de 20 metros de alguma Estrada!
            $estrada = \App\Models\RuralEstrada::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_DWithin(geo::geography, $polyWKT::geography, 20)")
                ->first();

            $this->ruralLocalidadePreSelecionadaId = $localidade->id;
            $this->ruralEstradaPreSelecionadaId = $estrada ? $estrada->id : null;

            $this->mountAction('criarRuralPonte');
        } elseif ($entityType === 'rural_ponto_interesse') {

            $polyWKT = "ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($geoJson) . "'), 4326)";

            $localidade = \App\Models\RuralLocalidade::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Intersects(geo, $polyWKT)")
                ->first();

            if (!$localidade) {
                \Filament\Notifications\Notification::make()
                    ->title('Fora de Localidade!')
                    ->body('Um Ponto de Interesse deve estar inserido dentro dos limites de uma Localidade / Distrito rural.')
                    ->danger()
                    ->send();

                $this->dispatch('limpar-rascunho-mapa');
                return;
            }

            $this->ruralLocalidadePreSelecionadaId = $localidade->id;
            $this->mountAction('criarRuralPontoInteresse');
        } elseif ($entityType === 'bairro') {

            // Simples e direto: desenhou, abriu a modal de criar!
            $this->mountAction('criarBairro');
        } elseif ($entityType === 'loteamento') {

            $this->mountAction('criarLoteamento');
        } elseif ($entityType === 'quadra') {

            $polyWKT = "ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($geoJson) . "'), 4326)";

            // 1. Procura o Bairro que contém a quadra (com 1m² de tolerância de borda)
            $bairro = \App\Models\Bairro::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Area(ST_Difference($polyWKT, geo::geometry)::geography) <= 1.0")
                ->first();

            // 2. Procura o Loteamento que contém a quadra (com 1m² de tolerância)
            $loteamento = \App\Models\Loteamento::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Area(ST_Difference($polyWKT, geo::geometry)::geography) <= 1.0")
                ->first();

            // REGRA: Tem que estar DENTRO de pelo menos um dos dois!
            if (!$bairro && !$loteamento) {
                \Filament\Notifications\Notification::make()
                    ->title('Fora dos Limites!')
                    ->body('Uma Quadra deve ser desenhada inteiramente DENTRO de um Bairro ou Loteamento existente.')
                    ->danger()
                    ->send();

                $this->dispatch('limpar-rascunho-mapa');
                return;
            }

            // 3. Identifica automaticamente o Perímetro Urbano (basta cruzar ou estar dentro)
            $perimetro = \App\Models\PerimetroUrbano::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Intersects(geo, $polyWKT)")
                ->first();

            // Injeta as descobertas nas variáveis da Trait para auto-preencher o formulário
            $this->quadraBairroPreSelecionadoId = $bairro ? $bairro->id : null;
            $this->quadraLoteamentoPreSelecionadoId = $loteamento ? $loteamento->id : null;
            $this->quadraPerimetroPreSelecionadoId = $perimetro ? $perimetro->id : null;

            $this->mountAction('criarQuadra');
        } elseif ($entityType === 'zona') {
            $this->mountAction('criarZona');
        } elseif ($entityType === 'ponto_panoramico') {
            $this->mountAction('criarPontoPanoramico');
        }
    }

    public function habilitarEdicaoGeometria()
    {
        $this->dispatch('iniciar-edicao-geometria', ['id' => $this->loteAtivoId]);
        $this->fecharFicha();
    }

    #[On('salvarNovaGeometria')]
    public function salvarNovaGeometria($id, $geoJson)
    {
        $lote = Lote::where('id', $id)->where('tenant_id', $this->tenantId)->first();
        if ($lote) {
            $polyWKT = "ST_GeomFromGeoJSON('" . json_encode($geoJson) . "')";
            $sobreposicao = Lote::where('tenant_id', $this->tenantId)->where('id', '!=', $id)
                ->whereRaw("ST_Intersects(geo, $polyWKT)")
                ->whereRaw("ST_Area(ST_Intersection(geo, $polyWKT)::geography) > 0.1")
                ->exists();

            if ($sobreposicao) {
                Notification::make()->title('Edição Cancelada')->danger()->send();
                $this->dispatch('desfazer-edicao-geometria');
                return;
            }

            $lote->update(['geo' => $geoJson]);
            DB::statement("UPDATE lotes SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$lote->id]);
            DB::statement("UPDATE unidade_imobiliarias SET geo = (SELECT ST_PointOnSurface(geo) FROM lotes WHERE id = ?) WHERE lote_id = ?", [$lote->id, $lote->id]);

            Notification::make()->title('Geometria Atualizada!')->success()->send();
        }
    }

    #[On('salvarNovaGeometriaEdificacao')]
    public function salvarNovaGeometriaEdificacao($id, $geoJson)
    {
        $edif = Edificacao::find($id);
        if ($edif) {
            $polyWKT = "ST_GeomFromGeoJSON('" . json_encode($geoJson) . "')";
            // Substituímos o ST_Within pelo ST_Difference com tolerância
            if (!Lote::where('id', $edif->lote_id)->whereRaw("ST_Area(ST_Difference($polyWKT, geo)::geography) <= 0.1")->exists()) {
                Notification::make()->title('Erro Topológico')->danger()->send();
                $this->dispatch('desfazer-edicao-geometria');
                return;
            }
            $edif->update(['geo' => $geoJson]);
            DB::statement("UPDATE edificacoes SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$edif->id]);
            $this->loteAreaConstruida = (float) Edificacao::where('lote_id', $this->loteAtivoId)->sum('area_geo');
            Notification::make()->title('Geometria Atualizada!')->success()->send();
            $this->mostrarEdificacoesLoteAtivo = false;
            $this->toggleEdificacoesLote();
        }
    }

    public function deletarArtefatoAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('deletarArtefato')
            ->requiresConfirmation()
            ->action(function () {
                if ($this->loteAtivoId) {
                    $lote = Lote::where('id', $this->loteAtivoId)->where('tenant_id', $this->tenantId)->first();
                    if ($lote) {
                        \App\Models\UnidadeImobiliaria::where('lote_id', $lote->id)->delete();
                        Edificacao::where('lote_id', $lote->id)->delete();
                        $lote->delete();
                        Notification::make()->title('Excluído!')->success()->send();
                        $this->dispatch('remover-lote-mapa', ['id' => $this->loteAtivoId]);
                        $this->fecharFicha();
                    }
                }
            });
    }

    #[On('abrirOpcoesEdificacao')]
    public function abrirOpcoesEdificacao($id)
    {
        $this->edificacaoAtivaId = $id;
        $this->mountAction('opcoesEdificacao');
    }

    #[On('abrirOpcoesLogradouro')]
    public function abrirOpcoesLogradouro($id)
    {
        $this->logradouroAtivoId = $id;
        $this->mountAction('opcoesLogradouro');
    }

    #[On('salvarNovaGeometriaLogradouro')]
    public function salvarNovaGeometriaLogradouro($id, $geoJson)
    {
        $logradouro = \App\Models\Logradouro::find($id);
        if ($logradouro) {
            $logradouro->update(['geo' => $geoJson]);
            // Opcional: DB::statement("UPDATE logradouros SET length_geo = ST_Length(geo::geography) WHERE id = ?", [$logradouro->id]);

            \Filament\Notifications\Notification::make()->title('Geometria da Rua Atualizada!')->success()->send();
        }
    }

    #[On('salvarNovaGeometriaPoste')]
    public function salvarNovaGeometriaPoste($id, $geoJson)
    {
        $poste = Poste::find($id);
        if ($poste) {
            $poste->update(['geo' => $geoJson]);

            \Filament\Notifications\Notification::make()
                ->title('Posição do Poste Atualizada!')
                ->success()
                ->send();
        }
    }

    #[On('abrirOpcoesPoste')]
    public function abrirOpcoesPoste($id)
    {
        $this->posteAtivoId = $id;
        $this->mountAction('opcoesPoste');
    }

    #[On('salvarNovaGeometriaArvore')]
    public function salvarNovaGeometriaArvore($id, $geoJson)
    {
        $arvore = Arvore::find($id);
        if ($arvore) {
            $arvore->update(['geo' => $geoJson]);
            \Filament\Notifications\Notification::make()
                ->title('Posição da Árvore Atualizada!')
                ->success()
                ->send();
        }
    }

    #[On('abrirOpcoesArvore')]
    public function abrirOpcoesArvore($id)
    {
        $this->arvoreAtivaId = $id;
        $this->mountAction('opcoesArvore');
    }

    #[On('abrirOpcoesCemiterio')]
    public function abrirOpcoesCemiterio($id)
    {
        $this->cemiterioAtivoId = $id;
        $this->mountAction('opcoesCemiterio');
    }

    #[On('salvarNovaGeometriaCemiterio')]
    public function salvarNovaGeometriaCemiterio($id, $geoJson)
    {
        $cemiterio = Cemiterio::find($id);
        if ($cemiterio) {
            $cemiterio->update(['geo' => $geoJson]);
            DB::statement("UPDATE cemiterios SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$cemiterio->id]);
            \Filament\Notifications\Notification::make()->title('Polígono Atualizado!')->success()->send();

            // NÃO PRECISA DISPARAR NADA AQUI! 
            // O Javascript (OpenLayers) já arrastou a linha lá na tela do usuário, 
            // não precisamos fazer a tela piscar para mostrar o que ele já está vendo!
        }
    }

    #[On('abrirOpcoesQuadraCemiterio')]
    public function abrirOpcoesQuadraCemiterio($id)
    {
        $this->quadraCemiterioAtivaId = $id;
        $this->mountAction('opcoesQuadraCemiterio');
    }

    #[On('salvarNovaGeometriaQuadraCemiterio')]
    public function salvarNovaGeometriaQuadraCemiterio($id, $geoJson)
    {
        $quadra = QuadraCemiterio::find($id);
        if ($quadra) {
            $polyWKT = "ST_GeomFromGeoJSON('" . json_encode($geoJson) . "')";

            // 🛑 MÁGICA TOPOLÓGICA: Impede de mover a quadra pra fora do cemitério dela
            $contido = \App\Models\Cemiterio::where('id', $quadra->cemiterio_id)
                ->whereRaw("ST_Intersects(geo, $polyWKT)")
                ->exists();

            if (!$contido) {
                \Filament\Notifications\Notification::make()
                    ->title('Erro Topológico')
                    ->body('Você não pode mover esta quadra para fora do cemitério ao qual ela pertence.')
                    ->danger()
                    ->send();

                $this->dispatch('desfazer-edicao-geometria'); // Manda o JS voltar o polígono pro lugar
                return;
            }

            $quadra->update(['geo' => $geoJson]);
            DB::statement("UPDATE quadras_cemiterio SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$quadra->id]);
            \Filament\Notifications\Notification::make()->title('Polígono da Quadra Atualizado!')->success()->send();
        }
    }

    #[On('abrirOpcoesLogradouroCemiterio')]
    public function abrirOpcoesLogradouroCemiterio($id)
    {
        $this->logradouroCemiterioAtivoId = $id;
        $this->mountAction('opcoesLogradouroCemiterio');
    }

    #[On('salvarNovaGeometriaLogradouroCemiterio')]
    public function salvarNovaGeometriaLogradouroCemiterio($id, $geoJson)
    {
        $logradouro = LogradouroCemiterio::find($id);
        if ($logradouro) {
            $polyWKT = "ST_GeomFromGeoJSON('" . json_encode($geoJson) . "')";

            $contido = \App\Models\Cemiterio::where('id', $logradouro->cemiterio_id)
                ->whereRaw("ST_Intersects(geo, $polyWKT)")
                ->exists();

            if (!$contido) {
                \Filament\Notifications\Notification::make()->title('Erro Topológico')->body('A rua não pode ser movida para fora do cemitério.')->danger()->send();
                $this->dispatch('desfazer-edicao-geometria');
                return;
            }

            $logradouro->update(['geo' => $geoJson]);
            \Filament\Notifications\Notification::make()->title('Caminho Atualizado!')->success()->send();
        }
    }

    #[On('abrirOpcoesJazigo')]
    public function abrirOpcoesJazigo($id)
    {
        $this->jazigoAtivoId = $id;
        $this->mountAction('opcoesJazigo');
    }

    #[On('salvarNovaGeometriaJazigo')]
    public function salvarNovaGeometriaJazigo($id, $geoJson)
    {
        $jazigo = Jazigo::find($id);
        if ($jazigo) {
            $polyWKT = "ST_GeomFromGeoJSON('" . json_encode($geoJson) . "')";

            // 🛑 Impede de mover o jazigo para fora da quadra dele
            $contido = \App\Models\QuadraCemiterio::where('id', $jazigo->quadra_cemiterio_id)
                ->whereRaw("ST_Intersects(geo, $polyWKT)")
                ->exists();

            if (!$contido) {
                \Filament\Notifications\Notification::make()->title('Erro Topológico')->body('Você não pode mover este jazigo para fora da sua respectiva quadra.')->danger()->send();
                $this->dispatch('desfazer-edicao-geometria');
                return;
            }

            $jazigo->update(['geo' => $geoJson]);
            DB::statement("UPDATE jazigos SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$jazigo->id]);
            \Filament\Notifications\Notification::make()->title('Polígono do Jazigo Atualizado!')->success()->send();
        }
    }

    // Substitua a função que recebe o gatilho:
    #[On('abrirModalNumeracao')]
    public function abrirModalNumeracao($logradouro_id, $logradouro_nome, $drawn_line)
    {
        $this->numLogradouroId = $logradouro_id;
        $this->numLogradouroNome = $logradouro_nome;
        $this->numDrawnLine = json_encode($drawn_line); // Guarda como texto pra query

        $this->mountAction('configurarNumeracaoAction');
    }

    public function configurarNumeracaoAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('configurarNumeracaoAction')
            ->hiddenLabel()
            ->modalHeading('Gerador de Numeração Predial')
            ->modalDescription(fn() => 'Configurando a métrica para: ' . $this->numLogradouroNome)
            ->modalSubmitActionLabel('Calcular Prévia no Mapa')
            ->modalIcon('heroicon-o-hashtag')
            ->form([
                \Filament\Forms\Components\Select::make('lado_par')
                    ->label('Qual lado da rua será a numeração PAR?')
                    ->options([
                        'right' => 'Lado Direito (Olhando do Marco Zero para o fim da rua)',
                        'left' => 'Lado Esquerdo (Olhando do Marco Zero para o fim da rua)',
                    ])
                    ->default('right')
                    ->required(),

                \Filament\Forms\Components\TextInput::make('numero_inicial')
                    ->label('Número Inicial / Constante')
                    ->helperText('Se a rua começar do zero, deixe 0. Se for um trecho final, coloque a metragem inicial (Ex: 1500).')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ])
            ->action(function (array $data) {

                $ladoPar = $data['lado_par'];
                $numInicial = (int) $data['numero_inicial'];

                // 🛑 A MÁGICA: USA O TRAJETO DESENHADO PELO USUÁRIO!
                $lotes = DB::select("
                    WITH drawn_line AS (
                        SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS geom
                    )
                    SELECT 
                        l.id, l.numero_lote as lote_nome,
                        ST_AsGeoJSON(ST_Centroid(l.geo)) as centroid_geo,
                        
                        -- Distância percorrida fiel à linha desenhada até o lote
                        ST_Length(
                            ST_LineSubstring(
                                d.geom, 
                                0, 
                                GREATEST(0.00001, LEAST(0.99999, ST_LineLocatePoint(d.geom, ST_Centroid(l.geo))))
                            )::geography
                        ) as distancia_metros,
                        
                        -- Coordenadas para Produto Vetorial (Par / Ímpar)
                        ST_X(ST_Centroid(l.geo)) as cx,
                        ST_Y(ST_Centroid(l.geo)) as cy,
                        ST_X(ST_ClosestPoint(d.geom, ST_Centroid(l.geo))) as rua_cx,
                        ST_Y(ST_ClosestPoint(d.geom, ST_Centroid(l.geo))) as rua_cy,
                        ST_X(ST_StartPoint(d.geom)) as start_x,
                        ST_Y(ST_StartPoint(d.geom)) as start_y
                    FROM lotes l
                    CROSS JOIN drawn_line d
                    WHERE l.tenant_id = ?
                    
                    -- 🛑 O TÚNEL RESTRITO DE 15 METROS: 
                    -- A distância do POLÍGONO do lote até a linha desenhada deve ser menor que 15m.
                    -- Isso pega apenas quem faz divisa com o trajeto e NUNCA pega o vizinho de trás!
                    AND ST_DWithin(l.geo::geography, d.geom::geography, 15)
                ", [
                    $this->numDrawnLine,
                    $this->tenantId
                ]);

                $this->resultadosNumeracao = [];

                foreach ($lotes as $l) {
                    $dist = round($l->distancia_metros);
                    $numTarget = $numInicial + $dist;

                    // Produto Vetorial usando o início da linha desenhada como referência
                    $vx_street = $l->rua_cx - $l->start_x;
                    $vy_street = $l->rua_cy - $l->start_y;
                    $vx_lot = $l->cx - $l->rua_cx;
                    $vy_lot = $l->cy - $l->rua_cy;

                    $crossProduct = ($vx_street * $vy_lot) - ($vy_street * $vx_lot);
                    $isLeft = $crossProduct > 0;
                    $isRight = $crossProduct <= 0;

                    $isLadoParCalculado = ($ladoPar === 'right' && $isRight) || ($ladoPar === 'left' && $isLeft);

                    if ($isLadoParCalculado && $numTarget % 2 !== 0) {
                        $numTarget += 1;
                    } elseif (!$isLadoParCalculado && $numTarget % 2 === 0) {
                        $numTarget += 1;
                    }

                    $this->resultadosNumeracao[] = [
                        'lote_id' => $l->id,
                        'numero_atual' => $l->lote_nome ?: 'S/N',
                        'novo_numero' => $numTarget,
                        'distancia' => $dist,
                        'geo' => json_decode($l->centroid_geo)
                    ];
                }

                $this->previewNumeracaoAtivo = true;

                // Manda pro JS desenhar na tela!
                $this->dispatch('mostrar-preview-numeracao', dados: $this->resultadosNumeracao);

                Notification::make()->title('Prévia Gerada!')->body('Revise os números no mapa.')->success()->send();
            });
    }

    public function confirmarNumeracaoAction()
    {
        /*  foreach ($this->resultadosNumeracao as $res) {
             Lote::where('id', $res['lote_id'])->update([
                 'numero_lote' => (string) $res['novo_numero'] 
             ]);
         } */

        Notification::make()->title('Método em análise!')->success()->send();

        $this->cancelarNumeracaoAction();
        $this->dispatch('atualizar-camada-lotes');
    }

    public function cancelarNumeracaoAction()
    {
        $this->previewNumeracaoAtivo = false;
        $this->resultadosNumeracao = [];
        $this->dispatch('limpar-preview-numeracao');
    }

    public function imprimirRelatorioNumeracao($imagemBase64)
    {
        $dados = $this->resultadosNumeracao;
        $rua = $this->numLogradouroNome;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.relatorio-numeracao', [
            'imagemMapa' => $imagemBase64,
            'dados' => $dados,
            'rua' => $rua,
            'data' => now()->format('d/m/Y H:i')
        ]);

        // Retorna o download direto na tela
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'relatorio-numeracao-' . \Illuminate\Support\Str::slug($rua) . '.pdf');
    }

    /**
     * Recebe a linha do mapa e consulta o Google Elevation API
     */
    #[\Livewire\Attributes\On('gerarPerfilAltimetrico')]
    public function gerarPerfilAltimetrico($coords)
    {
        $apiKey = env('GOOGLE_MAPS_API_KEY');

        if (!$apiKey) {
            \Filament\Notifications\Notification::make()->title('Aviso')->body('A chave GOOGLE_MAPS_API_KEY não está configurada no .env.')->warning()->send();
            return;
        }

        // Formata os pontos para a URL da API do Google (Lat,Lon|Lat,Lon...)
        $pathStr = collect($coords)->map(function ($c) {
            return $c[1] . ',' . $c[0]; // O Google exige a Latitude primeiro
        })->implode('|');

        // Pedimos 100 pontos de amostragem para o gráfico ficar com uma curva bem realista
        $samples = 100;
        $url = "https://maps.googleapis.com/maps/api/elevation/json?path={$pathStr}&samples={$samples}&key={$apiKey}";

        try {
            $response = \Illuminate\Support\Facades\Http::get($url);
            $data = $response->json();

            if ($data['status'] === 'OK') {
                $this->altimetriaData = $data['results'];
                $this->mountAction('verPerfilAltimetricoAction'); // Abre a Modal com o gráfico
            } else {
                \Filament\Notifications\Notification::make()->title('Erro na API')->body($data['error_message'] ?? $data['status'])->danger()->send();
            }
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()->title('Erro de Conexão')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * Modal que renderiza o gráfico de relevo usando Chart.js via Alpine.js
     */
    public function verPerfilAltimetricoAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('verPerfilAltimetrico')
            ->modalHeading('Perfil Altimétrico do Terreno')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar Relatório')
            ->modalWidth('4xl')
            ->modalContent(function () {
                $bladeView = <<<'BLADE'
                <div>
                    {{-- Armazena os dados de forma segura e invisível para o JS ler sem quebrar aspas --}}
                    <script type="application/json" id="altimetria-data">
                        {!! json_encode($altimetriaData) !!}
                    </script>

                    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400 border-l-4 border-emerald-500 pl-3">
                        O corte topográfico abaixo foi processado usando a malha de satélites e dados de radar <strong>SRTM (Shuttle Radar Topography Mission)</strong>. Gráfico gerado a partir de <strong>{{ count($altimetriaData) }} pontos</strong> de amostragem.
                    </div>
                    
                    {{-- A Mágica do Alpine.js: Ele percebe quando a div entra na tela e roda o gráfico --}}
                    <div class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm" style="height: 350px;"
                        x-data="{
                            init() {
                                if (typeof Chart === 'undefined') {
                                    let script = document.createElement('script');
                                    script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                                    script.onload = () => this.drawChart();
                                    document.head.appendChild(script);
                                } else {
                                    this.drawChart();
                                }
                            },
                            drawChart() {
                                setTimeout(() => {
                                    const canvas = document.getElementById('altimetriaChart');
                                    const dataElement = document.getElementById('altimetria-data');
                                    if(!canvas || !dataElement) return;
                                    
                                    const rawData = JSON.parse(dataElement.textContent);
                                    const ctx = canvas.getContext('2d');
                                    
                                    const labels = rawData.map((d, index) => (index * (100 / rawData.length)).toFixed(0) + '%');
                                    const elevations = rawData.map(d => d.elevation.toFixed(2));

                                    if(window.altimetriaChartInstance) {
                                        window.altimetriaChartInstance.destroy();
                                    }

                                    window.altimetriaChartInstance = new Chart(ctx, {
                                        type: 'line',
                                        data: {
                                            labels: labels,
                                            datasets: [{
                                                label: 'Altitude (m)',
                                                data: elevations,
                                                borderColor: '#10b981',
                                                backgroundColor: 'rgba(16, 185, 129, 0.2)',
                                                borderWidth: 3,
                                                fill: true,
                                                tension: 0.4,
                                                pointRadius: 0,
                                                pointHoverRadius: 8,
                                                pointHoverBackgroundColor: '#047857'
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                        
                                            interaction: {
                                                mode: 'index',
                                                intersect: false,
                                            },

                                            plugins: {
                                                legend: { display: false },
                                                tooltip: {
                                                    backgroundColor: 'rgba(17, 24, 39, 0.9)',
                                                    padding: 12,
                                                    titleFont: { size: 13 },
                                                    bodyFont: { size: 14, weight: 'bold' },
                                                    callbacks: {
                                                        label: function(context) { return 'Altitude: ' + context.parsed.y + ' metros acima do nível do mar'; }
                                                    }
                                                }
                                            },
                                            scales: {
                                                y: {
                                                    title: { display: true, text: 'Altitude (Nível do Mar)', font: { weight: 'bold' } },
                                                    suggestedMin: Math.min(...elevations) - 2,
                                                    suggestedMax: Math.max(...elevations) + 2
                                                },
                                                x: { ticks: { maxTicksLimit: 10 } }
                                            }
                                        }
                                    });
                                }, 150); // Delay milimétrico só pro DOM assentar
                            }
                        }"
                    >
                        <canvas id="altimetriaChart"></canvas>
                    </div>
                </div>
                BLADE;

                return new \Illuminate\Support\HtmlString(\Illuminate\Support\Facades\Blade::render($bladeView, ['altimetriaData' => $this->altimetriaData]));
            });
    }

    /**
     * MOTOR POSTGIS: Recebe a linha do mapa, valida conflitos e fatia o polígono
     */
    #[On('processarDesmembramentoLote')]
    public function processarDesmembramentoLote($loteId, $linhaCorte)
    {
        $this->loteParaDesmembrarId = $loteId;
        $this->linhaDeCorteGeoJson = json_encode($linhaCorte);

        // 1. VALIDAÇÃO RIGOROSA: A linha cruza alguma edificação DESTE LOTE? (ST_Intersects)
        $conflito = DB::selectOne("
            WITH linha AS (
                SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS geom
            )
            SELECT e.id 
            FROM edificacoes e CROSS JOIN linha l
            WHERE e.tenant_id = ?
            AND e.lote_id = ? -- Otimização: Testa apenas as casas do lote que está sendo cortado
            AND ST_Intersects(e.geo::geometry, l.geom)
            LIMIT 1
        ", [$this->linhaDeCorteGeoJson, $this->tenantId, $this->loteParaDesmembrarId]);

        if ($conflito) {
            \Filament\Notifications\Notification::make()
                ->title('Operação Ilegal 🛑')
                ->body('O traçado do desmembramento cruza uma Edificação existente! Refaça o desenho contornando a construção (criando um corredor/servidão).')
                ->danger()
                ->persistent()
                ->send();
            return;
        }

        // 2. A MÁGICA DO CORTE: Extrai o polígono puro e fatia usando a Linha (ST_Split)
        $fatias = DB::select("
            WITH linha AS (
                SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS geom
            ),
            lote_bruto AS (
                SELECT geo::geometry AS geom FROM lotes WHERE id = ?
            ),
            lote_poly AS (
                -- Tira a blindagem de MultiPolygon para a faca do ST_Split funcionar sem dar Erro 500
                SELECT (ST_Dump(geom)).geom AS geom FROM lote_bruto LIMIT 1
            ),
            cortado AS (
                SELECT (ST_Dump(ST_Split(lote_poly.geom, linha.geom))).geom AS parte
                FROM lote_poly CROSS JOIN linha
            )
            SELECT 
                ST_AsGeoJSON(parte) as geojson,
                ST_Area(parte::geography) as area_m2
            FROM cortado
        ", [$this->linhaDeCorteGeoJson, $this->loteParaDesmembrarId]);

        // Se a linha não atravessou o lote de um lado ao outro, não dividiu.
        if (count($fatias) < 2) {
            \Filament\Notifications\Notification::make()
                ->title('Corte Inválido ⚠️')
                ->body('A linha não dividiu o lote. Inicie o traço FORA do lote e dê os dois cliques finais do outro lado, FORA do lote.')
                ->warning()
                ->send();
            return;
        }

        // 3. Organiza os pedaços: o MAIOR pedaço fica como Lote Original, o menor vira Lote Novo
        $arrayFatias = json_decode(json_encode($fatias), true);
        usort($arrayFatias, fn($a, $b) => $b['area_m2'] <=> $a['area_m2']);

        $this->previewDesmembramento = $arrayFatias;

        // Abre a modal para o Engenheiro confirmar o ato!
        $this->mountAction('confirmarDesmembramentoAction');
    }

    /**
     * MODAL DE CONFIRMAÇÃO E SALVAMENTO NO BANCO (UPDATE + INSERT)
     */
    public function confirmarDesmembramentoAction(): \Filament\Actions\Action
    {
        // 🛑 CORREÇÃO: O nome da Action agora bate exatamente com a chamada do mountAction!
        return \Filament\Actions\Action::make('confirmarDesmembramentoAction')
            ->modalHeading('Confirmar Desmembramento de Lote')
            ->modalDescription(fn() => new \Illuminate\Support\HtmlString(
                "O sistema fatiou a geometria perfeitamente.<br><br>" .
                    "🔸 <b>Lote Original (Área Remanescente):</b> " . number_format($this->previewDesmembramento[0]['area_m2'] ?? 0, 2, ',', '.') . " m²<br>" .
                    "🔹 <b>Novo Lote Gerado (Desmembrado):</b> " . number_format($this->previewDesmembramento[1]['area_m2'] ?? 0, 2, ',', '.') . " m²<br><br>" .
                    "Deseja gravar as alterações no banco de dados e redistribuir as edificações?"
            ))
            ->modalSubmitActionLabel('✂️ Confirmar Corte')
            ->color('warning')
            ->action(function () {
                $loteOriginal = \App\Models\Lote::find($this->loteParaDesmembrarId);

                $partePrincipal = $this->previewDesmembramento[0];
                $parteDesmembrada = $this->previewDesmembramento[1];

                // 1. UPDATE: Reduz o tamanho do Lote Original 
                // 🛑 CORREÇÃO: Apenas decodificamos a string do PostGIS para Array, e o seu Model (setGeoAttribute) faz o resto!
                $loteOriginal->geo = json_decode($partePrincipal['geojson'], true);
                $loteOriginal->area_geo = $partePrincipal['area_m2'];
                $loteOriginal->save();

                // 2. INSERT: Cria o NOVO Lote (Clone com nova Geometria)
                $novoLote = $loteOriginal->replicate();
                $novoLote->sequential_id = null;
                $novoLote->code = (string) \Illuminate\Support\Str::uuid();
                $novoLote->numero_lote = ($loteOriginal->numero_lote ?? 'S/N') . ' (Desmembrado)';

                // 🛑 CORREÇÃO AQUI TAMBÉM
                $novoLote->geo = json_decode($parteDesmembrada['geojson'], true);
                $novoLote->area_geo = $parteDesmembrada['area_m2'];
                $novoLote->save();

                // 3. INJEÇÃO DE REGRA DE NEGÓCIO: Cria a Unidade Imobiliária nova
                \App\Models\UnidadeImobiliaria::create([
                    'tenant_id' => $novoLote->tenant_id,
                    'code' => (string) \Illuminate\Support\Str::uuid(),
                    'lote_id' => $novoLote->id,
                ]);

                // 4. ATUALIZAÇÃO MÁGICA DAS EDIFICAÇÕES: 
                DB::statement("
                    UPDATE edificacoes 
                    SET lote_id = ? 
                    WHERE lote_id = ? 
                    AND ST_Contains(
                        ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), 
                        ST_Centroid(geo::geometry)
                    )
                ", [$novoLote->id, $loteOriginal->id, $parteDesmembrada['geojson']]);

                \Filament\Notifications\Notification::make()->title('Desmembramento Concluído!')->success()->send();

                // 5. Limpa a tela e desenha os lotes novos no mapa instantaneamente
                $this->dispatch('remover-lote-mapa', ['id' => $loteOriginal->id]);
                $this->dispatch('adicionar-lote-mapa', ['id' => $loteOriginal->id, 'numero_lote' => $loteOriginal->numero_lote, 'geo' => json_decode($partePrincipal['geojson'])]);
                $this->dispatch('adicionar-lote-mapa', ['id' => $novoLote->id, 'numero_lote' => $novoLote->numero_lote, 'geo' => json_decode($parteDesmembrada['geojson'])]);

                $this->fecharFicha(); // Recolhe a aba lateral
            });
    }

    /**
     * MOTOR POSTGIS: Verifica se são vizinhos e realiza a solda (ST_Union)
     */
    #[On('processarUnificacaoLotes')]
    public function processarUnificacaoLotes($lotePrincipalId, $loteSecundarioId)
    {
        $this->lotePrincipalToUnifyId = $lotePrincipalId;
        $this->loteSecundarioToUnifyId = $loteSecundarioId;

        // 1. VALIDAÇÃO ESPACIAL: Eles encostam um no outro? (ST_Intersects pega as divisas que se tocam)
        $vizinhos = DB::selectOne("
            SELECT ST_Intersects(l1.geo::geometry, l2.geo::geometry) as sao_vizinhos
            FROM lotes l1, lotes l2
            WHERE l1.id = ? AND l2.id = ?
        ", [$lotePrincipalId, $loteSecundarioId]);

        if (!$vizinhos || !$vizinhos->sao_vizinhos) {
            \Filament\Notifications\Notification::make()
                ->title('Ação Inválida 🛑')
                ->body('Os lotes selecionados não fazem divisa um com o outro! Só é possível unificar lotes lindeiros (vizinhos).')
                ->danger()->send();
            return;
        }

        // 2. A SOLDA GEOMÉTRICA: Une os polígonos e soma as áreas
        $uniao = DB::selectOne("
            WITH poly1 AS (SELECT geo::geometry as geom FROM lotes WHERE id = ?),
                 poly2 AS (SELECT geo::geometry as geom FROM lotes WHERE id = ?)
            SELECT 
                ST_AsGeoJSON(ST_Union(poly1.geom, poly2.geom)) as geojson,
                ST_Area(ST_Union(poly1.geom, poly2.geom)::geography) as nova_area
            FROM poly1, poly2
        ", [$lotePrincipalId, $loteSecundarioId]);

        $this->previewUnificacao = [
            'geojson' => $uniao->geojson,
            'nova_area' => $uniao->nova_area
        ];

        // 3. Abre a Modal pedindo o novo código
        $this->mountAction('confirmarUnificacaoAction');
    }

    /**
     * MODAL DE CONFIRMAÇÃO DA UNIFICAÇÃO (Salva no Banco e Herda Edificações)
     */
    public function confirmarUnificacaoAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('confirmarUnificacaoAction')
            ->modalHeading('Confirmar Unificação de Lotes')
            ->modalDescription(function () {
                $lote1 = \App\Models\Lote::find($this->lotePrincipalToUnifyId);
                $lote2 = \App\Models\Lote::find($this->loteSecundarioToUnifyId);
                $novaArea = number_format($this->previewUnificacao['nova_area'] ?? 0, 2, ',', '.');

                return new \Illuminate\Support\HtmlString(
                    "O Lote <b>{$lote2->numero_lote}</b> será anexado/absorvido pelo Lote Principal <b>{$lote1->numero_lote}</b>.<br><br>" .
                        "✅ <b>Nova Área Total:</b> {$novaArea} m²<br>" .
                        "✅ Todas as casas e unidades imobiliárias do lote secundário serão migradas automaticamente.<br><br>" .
                        "Por favor, confirme o número final para esta nova grande parcela territorial:"
                );
            })
            ->form([
                \Filament\Forms\Components\TextInput::make('novo_numero_lote')
                    ->label('Número do Lote Unificado')
                    ->required()
                    ->default(fn() => \App\Models\Lote::find($this->lotePrincipalToUnifyId)->numero_lote),
            ])
            ->modalSubmitActionLabel('🔗 Confirmar Unificação')
            ->color('success')
            ->action(function (array $data) {
                $lotePrincipal = \App\Models\Lote::find($this->lotePrincipalToUnifyId);
                $loteSecundario = \App\Models\Lote::find($this->loteSecundarioToUnifyId);
                $loteSecundarioIdParaDeletar = $loteSecundario->id;

                $novaTestada = ($lotePrincipal->main_facade_length ?? 0) + ($loteSecundario->main_facade_length ?? 0);

                // 1. ATUALIZA O LOTE PRINCIPAL (Com a solda do PostGIS)
                // Usando o seu mutator json_decode que validamos na etapa anterior!
                $lotePrincipal->geo = json_decode($this->previewUnificacao['geojson'], true);
                $lotePrincipal->area_geo = $this->previewUnificacao['nova_area'];
                $lotePrincipal->numero_lote = $data['novo_numero_lote'];
                $lotePrincipal->main_facade_length = $novaTestada;
                $lotePrincipal->save();

                // 2. MIGRAÇÃO DE HERANÇA (Transfere Unidades e Edificações)
                \App\Models\UnidadeImobiliaria::where('lote_id', $loteSecundarioIdParaDeletar)->update(['lote_id' => $lotePrincipal->id]);
                \App\Models\Edificacao::where('lote_id', $loteSecundarioIdParaDeletar)->update(['lote_id' => $lotePrincipal->id]);

                // 3. APAGA O LOTE SECUNDÁRIO (Sofre SoftDelete e sai do mapa)
                $loteSecundario->delete();

                \Filament\Notifications\Notification::make()->title('Unificação Concluída!')->success()->send();

                // 4. ATUALIZA O MAPA EM TEMPO REAL
                $this->dispatch('remover-lote-mapa', ['id' => $loteSecundarioIdParaDeletar]); // Tira o velho
                $this->dispatch('remover-lote-mapa', ['id' => $lotePrincipal->id]); // Tira o principal antigo

                // Insere o Lote Principal novo e gigante!
                $this->dispatch('adicionar-lote-mapa', [
                    'id' => $lotePrincipal->id,
                    'numero_lote' => $lotePrincipal->numero_lote,
                    'geo' => json_decode($this->previewUnificacao['geojson'])
                ]);

                $this->fecharFicha();
            });
    }

    /**
     * Ação: Abrir o Visualizador 3D de Nuvem de Pontos (Potree) com Fallback Inteligente
     */
    public function abrirNuvemPontosAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('abrirNuvemPontosAction')
            ->modalHeading('Visualizador 3D - Nuvem de Pontos (LiDAR)')
            ->modalWidth('5xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar Visualizador')
            ->modalContent(function () {

                // 1. O SISTEMA PROCURA O ARQUIVO REAL DO MUNICÍPIO NA PASTA PUBLIC
                // Estrutura esperada: public/nuvem-pontos/bom-principio/index.html
                $pastaMunicipio = "nuvem-pontos/{$this->tenantSlug}";
                $caminhoFisico = public_path("{$pastaMunicipio}/index.html");

                // 2. A INTELIGÊNCIA DO FALLBACK
                if (file_exists($caminhoFisico)) {
                    // O voo de drone existe! Carrega o dado real da prefeitura.
                    $demoUrl = asset("{$pastaMunicipio}/index.html");
                    $mensagem = "Visualizando dados reais de escaneamento a laser do município.";
                    $corAviso = "emerald"; // Fica verdinho
                } else {
                    // A prefeitura ainda não contratou o voo. Carrega a demonstração da PoC.
                    $demoUrl = 'https://potree.github.io/potree/examples/annotations.html';
                    $mensagem = "Nuvem de pontos do município não detectada. Exibindo ambiente 3D de demonstração.";
                    $corAviso = "blue"; // Fica azul
                }

                $bladeView = <<<'BLADE'
                <div>
                    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400 border-l-4 border-{{ $corAviso }}-500 pl-3 py-1">
                        <b>{{ $mensagem }}</b><br>
                        Utilize o mouse para orbitar, o scroll para zoom e as ferramentas laterais para medição e fatiamento.
                    </div>
                    
                    {{-- O Container do iFrame 3D --}}
                    <div class="w-full rounded-xl overflow-hidden border border-gray-300 dark:border-gray-700 bg-black shadow-inner" style="height: 650px;">
                        <iframe src="{{ $demoUrl }}" class="w-full h-full border-0" allow="fullscreen"></iframe>
                    </div>
                </div>
                BLADE;

                return new \Illuminate\Support\HtmlString(\Illuminate\Support\Facades\Blade::render($bladeView, [
                    'demoUrl' => $demoUrl,
                    'mensagem' => $mensagem,
                    'corAviso' => $corAviso
                ]));
            });
    }

    #[On('abrirOpcoesSetorFiscal')]
    public function abrirOpcoesSetorFiscal($id)
    {
        $this->setorFiscalAtivoId = $id;
        $this->mountAction('opcoesSetorFiscal');
    }

    #[On('salvarNovaGeometriaSetorFiscal')]
    public function salvarNovaGeometriaSetorFiscal($id, $geoJson)
    {
        $setor = \App\Models\SetorFiscal::find($id);
        if ($setor) {
            $polyWKT = "ST_GeomFromGeoJSON('" . json_encode($geoJson) . "')";

            // Validação de sobreposição com tolerância de 5m² para edição com Ímã (Ignorando a si mesmo)
            $sobreposicao = \App\Models\SetorFiscal::where('tenant_id', $this->tenantId)
                ->where('id', '!=', $setor->id)
                ->whereRaw("ST_Intersects(geo, $polyWKT)")
                ->whereRaw("ST_Area(ST_Intersection(geo::geometry, $polyWKT)::geography) > 5.0")
                ->exists();

            if ($sobreposicao) {
                \Filament\Notifications\Notification::make()
                    ->title('Edição Cancelada 🛑')
                    ->body('O polígono editado invade outro Setor Fiscal. A edição foi revertida.')
                    ->danger()
                    ->send();

                $this->dispatch('desfazer-edicao-geometria');
                return;
            }

            $setor->update(['geo' => $geoJson]);
            DB::statement("UPDATE setores_fiscais SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$setor->id]);
            \Filament\Notifications\Notification::make()->title('Geometria do Setor Atualizada!')->success()->send();
        }
    }

    /**
     * MÓDULO PGV - A MÁGICA DA SIMULAÇÃO ESPACIAL E FINANCEIRA
     */
    public function configurarPgvAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('configurarPgvAction')
            ->hiddenLabel()
            ->modalHeading('Simulador - Planta Genérica de Valores')
            ->modalDescription('Defina os parâmetros para simular o IPTU/Valor Venal. O sistema cruzará os Lotes com os Setores Fiscais desenhados no mapa.')
            ->modalSubmitActionLabel('Calcular no Mapa')
            ->modalIcon('heroicon-o-banknotes')
            ->form([
                \Filament\Forms\Components\Select::make('bairros')
                    ->label('Restringir por Bairro(s)')
                    ->options(\App\Models\Bairro::where('tenant_id', $this->tenantId)->pluck('name', 'id'))
                    ->multiple()
                    ->helperText('Deixe em branco para calcular a cidade inteira.'),

                \Filament\Forms\Components\TextInput::make('ano_vigente')
                    ->label('Ano de Referência')
                    ->numeric()
                    ->default(now()->year)
                    ->required(),
            ])
            ->action(function (array $data) {
                $bairros = $data['bairros'] ?? [];

                // MÁGICA POSTGIS: Pega os Lotes, cruza com o Setor Fiscal que ele cai dentro, e junta com a Tabela de Preços
                $query = "
                    SELECT 
                        l.id as lote_id, 
                        ST_AsGeoJSON(ST_Centroid(l.geo)) as centroid_geo,
                        l.area_geo,
                        COALESCE((SELECT SUM(area_geo) FROM edificacoes WHERE lote_id = l.id), 0) as area_construida,
                        p.valor_m2_terreno,
                        p.valor_m2_edificacao,
                        s.id as setor_id
                    FROM lotes l
                    JOIN setores_fiscais s ON ST_Intersects(ST_Centroid(l.geo), s.geo)
                    JOIN pgv_parametros p ON s.pgv_parametro_id = p.id
                    WHERE l.tenant_id = ? AND l.deleted_at IS NULL
                ";

                $bindings = [$this->tenantId];

                // Filtro opcional por bairro
                if (!empty($bairros)) {
                    $placeholders = implode(',', array_fill(0, count($bairros), '?'));
                    $query .= " AND l.quadra_id IN (SELECT id FROM quadras WHERE bairro_id IN ($placeholders))";
                    $bindings = array_merge($bindings, $bairros);
                }

                $lotes = DB::select($query, $bindings);

                $this->resultadosPgv = [];

                foreach ($lotes as $l) {
                    $valorTerreno = $l->area_geo * $l->valor_m2_terreno;
                    $valorEdificacao = $l->area_construida * $l->valor_m2_edificacao;
                    $total = $valorTerreno + $valorEdificacao;

                    $this->resultadosPgv[] = [
                        'lote_id' => $l->lote_id,
                        'setor_id' => $l->setor_id,
                        'ano_vigente' => $data['ano_vigente'],
                        'valor_terreno' => $valorTerreno,
                        'valor_edificacao' => $valorEdificacao,
                        'valor_total' => $total,
                        'valor_formatado' => 'R$ ' . number_format($total, 2, ',', '.'),
                        'geo' => json_decode($l->centroid_geo)
                    ];
                }

                if (count($this->resultadosPgv) === 0) {
                    Notification::make()->title('Sem resultados')->body('Nenhum lote cruzou com os Setores Fiscais cadastrados.')->warning()->send();
                    return;
                }

                $this->previewPgvAtivo = true;
                $this->dispatch('mostrar-preview-pgv', dados: $this->resultadosPgv);
                Notification::make()->title('Cálculo Realizado!')->body('Revise as etiquetas verdes no mapa.')->success()->send();
            });
    }

    public function cancelarPgvAction()
    {
        $this->previewPgvAtivo = false;
        $this->resultadosPgv = [];
        $this->dispatch('limpar-preview-pgv');
    }

    public function homologarPgvAction()
    {
        // Aqui nós faremos o INSERT na tabela lote_valores_historicos.
        // Já vou deixar estruturado para a sua chamada!

        foreach ($this->resultadosPgv as $pgv) {
            \App\Models\LoteValorHistorico::updateOrCreate(
                ['lote_id' => $pgv['lote_id'], 'ano_vigente' => $pgv['ano_vigente']],
                [
                    'tenant_id' => $this->tenantId,
                    'setor_fiscal_id' => $pgv['setor_id'],
                    'valor_terreno' => $pgv['valor_terreno'],
                    'valor_edificacao' => $pgv['valor_edificacao'],
                    'valor_total' => $pgv['valor_total'],
                ]
            );
        }

        Notification::make()->title('PGV Homologada!')->body('Os valores foram gravados no histórico financeiro com sucesso.')->success()->send();
        $this->cancelarPgvAction();
    }

    /* MÓDULO RURAL */
    #[On('abrirOpcoesRuralLocalidade')]
    public function abrirOpcoesRuralLocalidade($id)
    {
        $this->ruralLocalidadeAtivaId = $id;
        $this->mountAction('opcoesRuralLocalidade');
    }

    #[On('salvarNovaGeometriaRuralLocalidade')] // Crie este novo Listener
    public function salvarNovaGeometriaRuralLocalidade($id, $geoJson)
    {
        $reg = \App\Models\RuralLocalidade::find($id);
        if ($reg) {
            $reg->update(['geo' => $geoJson]);
            DB::statement("UPDATE rural_localidades SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$reg->id]);
            Notification::make()->title('Geometria da Localidade Atualizada!')->success()->send();
        }
    }

    #[On('abrirOpcoesRuralPropriedade')]
    public function abrirOpcoesRuralPropriedade($id)
    {
        $this->ruralPropriedadeAtivaId = $id;
        $this->mountAction('opcoesRuralPropriedade');
    }

    #[On('salvarNovaGeometriaRuralPropriedade')]
    public function salvarNovaGeometriaRuralPropriedade($id, $geoJson)
    {
        $reg = \App\Models\RuralPropriedade::find($id);
        if ($reg) {
            $polyWKT = "ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($geoJson) . "'), 4326)";

            // 🛑 REGRA 3: Verifica se o polígono arrastado continua dentro da sua localidade base
            $validacao = DB::selectOne("
                SELECT ST_Area(ST_Difference(
                    $polyWKT,
                    (SELECT geo::geometry FROM rural_localidades WHERE id = ?)
                )::geography) as area_fora
            ", [$reg->rural_localidade_id]);

            if ($validacao && $validacao->area_fora > 1.0) {
                \Filament\Notifications\Notification::make()
                    ->title('Erro Topológico')
                    ->body('Você não pode mover esta propriedade para fora dos limites da sua Localidade / Distrito atual.')
                    ->danger()->send();

                $this->dispatch('desfazer-edicao-geometria');
                return;
            }

            $reg->update(['geo' => $geoJson]);
            DB::statement("UPDATE rural_propriedades SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$reg->id]);
            \Filament\Notifications\Notification::make()->title('Geometria da Propriedade Atualizada!')->success()->send();
        }
    }

    #[On('abrirOpcoesRuralEstrada')]
    public function abrirOpcoesRuralEstrada($id)
    {
        $this->ruralEstradaAtivaId = $id;
        $this->mountAction('opcoesRuralEstrada');
    }

    #[On('salvarNovaGeometriaRuralEstrada')]
    public function salvarNovaGeometriaRuralEstrada($id, $geoJson)
    {
        $reg = \App\Models\RuralEstrada::find($id);
        if ($reg) {
            $reg->update(['geo' => $geoJson]);
            // O recalculo mágico de distância em metros sempre que a linha for puxada para o lado
            DB::statement("UPDATE rural_estradas SET extensao_geo = ST_Length(geo::geography) WHERE id = ?", [$reg->id]);
            Notification::make()->title('Traçado da Estrada Atualizado!')->success()->send();
        }
    }

    #[On('abrirOpcoesRuralHidrografia')]
    public function abrirOpcoesRuralHidrografia($id)
    {
        $this->ruralHidrografiaAtivaId = $id;
        $this->mountAction('opcoesRuralHidrografia');
    }

    #[On('salvarNovaGeometriaRuralHidrografia')]
    public function salvarNovaGeometriaRuralHidrografia($id, $geoJson)
    {
        $reg = \App\Models\RuralHidrografia::find($id);
        if ($reg) {
            $reg->update(['geo' => $geoJson]);
            Notification::make()->title('Geometria da Hidrografia Atualizada!')->success()->send();
        }
    }

    #[On('abrirOpcoesRuralPonte')]
    public function abrirOpcoesRuralPonte($id)
    {
        $this->ruralPonteAtivaId = $id;
        $this->mountAction('opcoesRuralPonte');
    }

    #[On('salvarNovaGeometriaRuralPonte')]
    public function salvarNovaGeometriaRuralPonte($id, $geoJson)
    {
        $reg = \App\Models\RuralPonte::find($id);
        if ($reg) {
            $reg->update(['geo' => $geoJson]);
            Notification::make()->title('Posição da Ponte Atualizada!')->success()->send();
        }
    }

    #[On('abrirOpcoesRuralPontoInteresse')]
    public function abrirOpcoesRuralPontoInteresse($id)
    {
        $this->ruralPontoInteresseAtivaId = $id;
        $this->mountAction('opcoesRuralPontoInteresse');
    }

    #[On('salvarNovaGeometriaRuralPontoInteresse')]
    public function salvarNovaGeometriaRuralPontoInteresse($id, $geoJson)
    {
        $reg = \App\Models\RuralPontoInteresse::find($id);
        if ($reg) {
            $reg->update(['geo' => $geoJson]);
            Notification::make()->title('Localização do Ponto Atualizada!')->success()->send();
        }
    }

    // --- MÓDULO BAIRROS ---
    #[On('abrirOpcoesBairro')]
    public function abrirOpcoesBairro($id)
    {
        $this->bairroAtivoId = $id;
        $this->mountAction('opcoesBairro');
    }

    #[On('salvarNovaGeometriaBairro')]
    public function salvarNovaGeometriaBairro($id, $geoJson)
    {
        $reg = \App\Models\Bairro::find($id);
        if ($reg) {
            $reg->update(['geo' => $geoJson]);

            // Tenta atualizar a área (ignora silenciosamente se a coluna area_geo não existir em bairros)
            try {
                DB::statement("UPDATE bairros SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$reg->id]);
            } catch (\Exception $e) {
            }

            \Filament\Notifications\Notification::make()->title('Limites do Bairro Atualizados!')->success()->send();
        }
    }

    // --- MÓDULO LOTEAMENTOS ---
    #[On('abrirOpcoesLoteamento')]
    public function abrirOpcoesLoteamento($id)
    {
        $this->loteamentoAtivoId = $id;
        $this->mountAction('opcoesLoteamento');
    }

    #[On('salvarNovaGeometriaLoteamento')]
    public function salvarNovaGeometriaLoteamento($id, $geoJson)
    {
        $reg = \App\Models\Loteamento::find($id);
        if ($reg) {
            $reg->update(['geo' => $geoJson]);

            // Tenta atualizar a área via PostGIS
            try {
                DB::statement("UPDATE loteamentos SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$reg->id]);
            } catch (\Exception $e) {
            }

            \Filament\Notifications\Notification::make()->title('Limites do Loteamento Atualizados!')->success()->send();
        }
    }

    // --- MÓDULO QUADRAS URBANAS ---
    #[On('abrirOpcoesQuadra')]
    public function abrirOpcoesQuadra($id)
    {
        $this->quadraAtivaId = $id;
        $this->mountAction('opcoesQuadra');
    }

    #[On('salvarNovaGeometriaQuadra')]
    public function salvarNovaGeometriaQuadra($id, $geoJson)
    {
        $reg = \App\Models\Quadra::find($id);
        if ($reg) {
            $polyWKT = "ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON('" . json_encode($geoJson) . "'), 4326))";

            // 🛑 NOVA REGRA TOPOLÓGICA: A Quadra não pode abandonar seus lotes!
            // Verifica se algum lote desta quadra ficará de fora da nova geometria (Tolerância de 1m²)
            $lotesOrfaos = DB::selectOne("
                SELECT count(*) as qtd
                FROM lotes
                WHERE quadra_id = ?
                AND tenant_id = ?
                AND deleted_at IS NULL
                AND ST_Area(ST_Difference(geo::geometry, $polyWKT)::geography) > 1.0
            ", [$reg->id, $this->tenantId]);

            if ($lotesOrfaos && $lotesOrfaos->qtd > 0) {
                \Filament\Notifications\Notification::make()
                    ->title('Erro Topológico: Lotes Órfãos!')
                    ->body("A nova geometria deixaria {$lotesOrfaos->qtd} lote(s) para fora da quadra. Mova os lotes primeiro ou ajuste o traçado da quadra para englobá-los.")
                    ->danger()
                    ->send();

                $this->dispatch('desfazer-edicao-geometria');
                return;
            }

            // Descobre em qual Bairro a quadra caiu agora
            $bairro = \App\Models\Bairro::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Area(ST_Difference($polyWKT, geo::geometry)::geography) <= 1.0")
                ->first();

            // Descobre em qual Loteamento a quadra caiu agora
            $loteamento = \App\Models\Loteamento::where('tenant_id', $this->tenantId)
                ->whereRaw("ST_Area(ST_Difference($polyWKT, geo::geometry)::geography) <= 1.0")
                ->first();

            if (!$bairro && !$loteamento) {
                \Filament\Notifications\Notification::make()->title('Fora dos Limites!')->body('A Quadra foi arrastada para fora de um Bairro/Loteamento.')->danger()->send();
                $this->dispatch('desfazer-edicao-geometria');
                return;
            }

            $perimetro = \App\Models\PerimetroUrbano::where('tenant_id', $this->tenantId)->whereRaw("ST_Intersects(geo, $polyWKT)")->first();

            $reg->update([
                'geo' => $geoJson,
                'bairro_id' => $bairro ? $bairro->id : null,
                'loteamento_id' => $loteamento ? $loteamento->id : null,
                'perimetro_id' => $perimetro ? $perimetro->id : null,
            ]);

            try {
                DB::statement("UPDATE quadras SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$reg->id]);
            } catch (\Exception $e) {
            }
            \Filament\Notifications\Notification::make()->title('Limites e Vínculos Atualizados!')->success()->send();
        }
    }

    /**
     * MOTOR POSTGIS CAD: Fatia genérica de qualquer polígono (Retorna para a Vitrine do JS)
     */
    #[On('processarCorteGenerico')]
    public function processarCorteGenerico($polygonGeoJson, $lineGeoJson, $layerOrigem)
    {
        // A MÁGICA DO CORTE: Extrai o polígono puro e fatia usando a Linha (ST_Split)
        $fatias = DB::select("
            WITH linha AS (
                SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS geom
            ),
            poly_base AS (
                SELECT ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) AS geom
            ),
            poly_dump AS (
                -- Tira a blindagem de MultiPolygon para a faca do ST_Split funcionar
                SELECT (ST_Dump(geom)).geom AS geom FROM poly_base LIMIT 1
            ),
            cortado AS (
                SELECT (ST_Dump(ST_Split(poly_dump.geom, linha.geom))).geom AS parte
                FROM poly_dump CROSS JOIN linha
            )
            SELECT 
                ST_AsGeoJSON(parte) as geojson,
                ST_Area(parte::geography) as area_m2
            FROM cortado
        ", [json_encode($lineGeoJson), json_encode($polygonGeoJson)]);

        // Se a linha não atravessou de um lado ao outro, o banco não consegue cortar!
        if (count($fatias) < 2) {
            \Filament\Notifications\Notification::make()
                ->title('Corte Inválido ⚠️')
                ->body('A linha não dividiu o polígono completamente. Desenhe a linha cruzando de um lado ao outro, começando e terminando FORA do polígono.')
                ->warning()
                ->send();

            $this->dispatch('cancelar-corte-generico');
            return;
        }

        // Organiza do maior pedaço para o menor
        $arrayFatias = json_decode(json_encode($fatias), true);
        usort($arrayFatias, fn($a, $b) => $b['area_m2'] <=> $a['area_m2']);

        // Devolve os pedaços cortados para o JavaScript exibir na vitrine
        $this->dispatch('mostrar-fatias-corte', fatias: $arrayFatias, layerOrigem: $layerOrigem);
    }

    // filtro avançado
    // filtro avançado
    // filtro avançado
    public function filtroAvancadoAction(): Action
    {
        return Action::make('filtroAvancado')
            ->label('Filtro Avançado')
            ->icon('heroicon-o-funnel')
            ->modalHeading('Construtor de Consultas GIS')
            ->modalDescription('Filtre artefatos baseado em atributos técnicos, cruzamentos geográficos ou desenho livre.')
            // 🛑 CORREÇÃO: Removido o fechamento dinâmico que causou o erro do $container
            ->modalSubmitActionLabel('Iniciar Consulta')
            ->modalWidth('md')
            ->form([
                // SELETOR DO TIPO DE CONSULTA
                Forms\Components\Radio::make('tipo_filtro')
                    ->label('Tipo de Consulta')
                    ->options([
                        'atributo' => 'Por Atributo (Texto / Número)',
                        'espacial' => 'Cruzamento Espacial (Ex: Lotes dentro de Bairro)',
                        'desenho' => 'Desenhar Área no Mapa (Polígono / Retângulo)' // 🟢 NOVA OPÇÃO!
                    ])
                    ->default('atributo')
                    ->live()
                    ->required(),

                // -------------------------------------------------------------
                // BLOCO 1: FILTRO POR ATRIBUTO 
                // -------------------------------------------------------------
                Forms\Components\Group::make([
                    Forms\Components\Select::make('layer')
                        ->label('Camada / Entidade')
                        ->options([
                            'lotes' => 'Lotes Urbanos',
                            'edificacoes' => 'Edificações',
                            'logradouros' => 'Logradouros',
                            'quadras' => 'Quadras',
                            'bairros' => 'Bairros',
                            'rural_propriedades' => 'Propriedades Rurais (CAR)',
                            'rural_estradas' => 'Estradas Rurais',
                            'rural_pontes' => 'Pontes Rurais',
                        ])
                        ->live()
                        ->required(fn (Forms\Get $get) => $get('tipo_filtro') === 'atributo'),

                    Forms\Components\Select::make('field')
                        ->label('Atributo (Campo de Busca)')
                        ->options(fn(Forms\Get $get): array => match ($get('layer')) {
                            'lotes' => ['area_geo' => 'Área em m²', 'main_facade_length' => 'Testada (m)', 'numero_lote' => 'Número do Lote'],
                            'edificacoes' => ['area_geo' => 'Área Construída (m²)', 'tipo' => 'Tipo de Uso'],
                            'rural_propriedades' => ['area_geo' => 'Área em m² (area_geo)', 'codigo_car' => 'Código CAR'],
                            'rural_estradas' => ['extensao_geo' => 'Extensão (m)', 'tipo_pavimento' => 'Tipo de Pavimento', 'condicao_trafego' => 'Condição'],
                            'rural_pontes' => ['capacidade_carga_toneladas' => 'Capacidade (Toneladas)', 'material_construcao' => 'Material'],
                            default => ['name' => 'Nome / Número'],
                        })
                        ->required(fn (Forms\Get $get) => $get('tipo_filtro') === 'atributo'),

                    Forms\Components\Select::make('operator')
                        ->label('Condição (Operador)')
                        ->options([
                            '=' => 'Igual a (=)',
                            '>' => 'Maior que (>)',
                            '<' => 'Menor que (<)',
                            '>=' => 'Maior ou igual (>=)',
                            '<=' => 'Menor ou igual (<=)',
                            'LIKE' => 'Contém o texto (LIKE)',
                            '!=' => 'Diferente de (!=)',
                        ])
                        ->default('=')
                        ->required(fn (Forms\Get $get) => $get('tipo_filtro') === 'atributo'),

                    Forms\Components\TextInput::make('value')
                        ->label('Valor da Condição')
                        ->placeholder('Ex: 250, Asfalto, Boa...')
                        ->required(fn (Forms\Get $get) => $get('tipo_filtro') === 'atributo'),
                ])->visible(fn(Forms\Get $get) => $get('tipo_filtro') === 'atributo'),

                // -------------------------------------------------------------
                // BLOCO 2: CRUZAMENTO ESPACIAL
                // -------------------------------------------------------------
                Forms\Components\Group::make([
                    Forms\Components\Select::make('spatial_target_layer')
                        ->label('O que você quer encontrar? (Alvo)')
                        ->options([
                            'lotes' => 'Lotes Urbanos',
                            'edificacoes' => 'Edificações',
                            'postes' => 'Postes / Iluminação',
                            'arvores' => 'Arborização',
                            'rural_propriedades' => 'Propriedades Rurais (CAR)',
                        ])
                        ->required(fn (Forms\Get $get) => $get('tipo_filtro') === 'espacial'),

                    Forms\Components\Select::make('spatial_operator')
                        ->label('Qual a relação topológica?')
                        ->options([
                            'ST_Within' => 'Que estejam DENTRO de',
                            'ST_Intersects' => 'Que tocam / Cruzam com',
                        ])
                        ->default('ST_Intersects')
                        ->required(fn (Forms\Get $get) => $get('tipo_filtro') === 'espacial'),

                    Forms\Components\Select::make('spatial_reference_layer')
                        ->label('Qual a área de referência?')
                        ->options([
                            'bairros' => 'Bairros',
                            'loteamentos' => 'Loteamentos',
                            'zonas' => 'Zonas Urbanas',
                            'rural_localidades' => 'Localidades Rurais / Distritos',
                            'cemiterios' => 'Cemitérios'
                        ])
                        ->live()
                        ->required(fn (Forms\Get $get) => $get('tipo_filtro') === 'espacial'),

                    Forms\Components\Select::make('spatial_reference_id')
                        ->label('Escolha o local específico')
                        ->options(function (Forms\Get $get) {
                            $refLayer = $get('spatial_reference_layer');
                            if (!$refLayer) return [];
                            return match ($refLayer) {
                                'bairros' => \App\Models\Bairro::where('tenant_id', $this->tenantId)->pluck('name', 'id')->toArray(),
                                'loteamentos' => \App\Models\Loteamento::where('tenant_id', $this->tenantId)->pluck('name', 'id')->toArray(),
                                'zonas' => \App\Models\Zona::where('tenant_id', $this->tenantId)->pluck('name', 'id')->toArray(),
                                'rural_localidades' => \App\Models\RuralLocalidade::where('tenant_id', $this->tenantId)->pluck('name', 'id')->toArray(),
                                'cemiterios' => \App\Models\Cemiterio::where('tenant_id', $this->tenantId)->pluck('name', 'id')->toArray(),
                                default => [],
                            };
                        })
                        ->searchable()
                        ->required(fn (Forms\Get $get) => $get('tipo_filtro') === 'espacial'),
                ])->visible(fn(Forms\Get $get) => $get('tipo_filtro') === 'espacial'),

                // -------------------------------------------------------------
                // BLOCO 3: DESENHO LIVRE (A EXIGÊNCIA NOVA!)
                // -------------------------------------------------------------
                Forms\Components\Group::make([
                    Forms\Components\Select::make('draw_target_layer')
                        ->label('O que você quer encontrar dentro da área?')
                        ->options([
                            'lotes' => 'Lotes Urbanos',
                            'edificacoes' => 'Edificações',
                            'postes' => 'Postes / Iluminação',
                            'arvores' => 'Arborização',
                            'rural_propriedades' => 'Propriedades Rurais (CAR)',
                        ])
                        ->required(fn (Forms\Get $get) => $get('tipo_filtro') === 'desenho'),

                    Forms\Components\Select::make('draw_shape')
                        ->label('Formato do Desenho')
                        ->options([
                            'Polygon' => 'Traçado Livre (Polígono)',
                            'Box' => 'Caixa (Retângulo)',
                        ])
                        ->default('Polygon')
                        ->required(fn (Forms\Get $get) => $get('tipo_filtro') === 'desenho'),

                ])->visible(fn(Forms\Get $get) => $get('tipo_filtro') === 'desenho'),

            ])
            ->action(function (array $data) {
                $this->filtroAvancadoAtivo = true;

                // 🟢 Lógica Bifurcada
                if ($data['tipo_filtro'] === 'desenho') {
                    // Manda uma ordem especial para o JS ligar a ferramenta de desenho
                    $this->dispatch('iniciar-desenho-filtro', dados: $data);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Modo de Desenho Ativo')
                        ->body('Clique no mapa para desenhar a área de pesquisa. Dê dois cliques para finalizar o polígono.')
                        ->info()
                        ->send();
                } else {
                    // O fluxo normal já existente que faz a pesquisa direta
                    $this->dispatch('executar-filtro-avancado', dados: $data);
                    \Filament\Notifications\Notification::make()->title('Analisando base de dados...')->info()->send();
                }
            });
    }

    public function limparFiltroAvancado()
    {
        $this->filtroAvancadoAtivo = false;
        $this->dispatch('limpar-filtro-avancado'); // Avisa o Javascript para limpar a tinta
        \Filament\Notifications\Notification::make()->title('Filtros removidos!')->success()->send();
    }

    /**
     * Atualiza a lista de Zonas no menu lateral dinamicamente
     */
    public function atualizarZonasTipos()
    {
        $this->zonasTipos = \App\Models\Zona::where('tenant_id', $this->tenantId)
            ->select('id', 'name', 'sigla', 'rgb')
            ->get()
            ->map(fn($zona) => [
                'id' => $zona->id,
                'name' => $zona->name,
                'sigla' => $zona->sigla,
                'rgb' => $zona->rgb ?? '(150,150,150)', // 👈 GARANTIA DE COR
            ])
            ->toArray();
    }

    #[On('abrirOpcoesZona')]
    public function abrirOpcoesZona($id)
    {
        $this->zonaAtivaId = $id;
        $this->mountAction('opcoesZona');
    }

    #[On('salvarNovaGeometriaZona')]
    public function salvarNovaGeometriaZona($id, $geoJson)
    {
        $zona = \App\Models\Zona::find($id);
        if ($zona) {
            $zona->update(['geo' => $geoJson]);

            try {
                \Illuminate\Support\Facades\DB::statement("UPDATE zonas SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$zona->id]);
            } catch (\Exception $e) {
            }

            \Filament\Notifications\Notification::make()->title('Limites da Zona Atualizados!')->success()->send();
        }
    }

    #[On('abrirOpcoesPontoPanoramico')]
    public function abrirOpcoesPontoPanoramico($id)
    {
        $this->pontoPanoramicoAtivoId = $id;
        $this->mountAction('opcoesPontoPanoramico');
    }

    #[On('salvarNovaGeometriaPontoPanoramico')]
    public function salvarNovaGeometriaPontoPanoramico($id, $geoJson)
    {
        $ponto = \App\Models\PontoPanoramico::find($id);
        if ($ponto) {
            $ponto->update(['geo' => $geoJson]);
            \Filament\Notifications\Notification::make()->title('Localização do Ponto 360º Atualizada!')->success()->send();
        }
    }
}
