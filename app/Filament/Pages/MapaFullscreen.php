<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Traits\HasLoteActions;
use App\Filament\Pages\Traits\HasEdificacaoActions;
use \App\Filament\Pages\Traits\HasLogradouroActions;
use App\Filament\Pages\Traits\HasPosteActions;
use App\Filament\Pages\Traits\HasArvoreActions;
use App\Filament\Pages\Traits\HasCemiterioActions;
use App\Filament\Pages\Traits\HasQuadraCemiterioActions;
use App\Filament\Pages\Traits\HasLogradouroCemiterioActions;
use App\Filament\Pages\Traits\HasJazigoActions;
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
    use HasJazigoActions; // <-- INJETAR AQUI


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
    public bool $showFicha = false;
    public float $loteAreaGeo = 0.0;
    public float $loteAreaConstruida = 0.0;
    public float $loteFacePrincipal = 0.0;

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

        $this->showFicha = true;
    }

    public function fecharFicha()
    {
        $this->showFicha = false;
    }

    #[On('abrirModalCriacao')]
    public function interceptarDesenho($entityType, $geoJson)
    {
        $this->geometriaRascunho = $geoJson;
        $polyWKT = "ST_GeomFromGeoJSON('" . json_encode($geoJson) . "')";
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

}