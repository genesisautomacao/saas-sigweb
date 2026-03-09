<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Traits\HasLoteActions;
use App\Filament\Pages\Traits\HasEdificacaoActions;
use \App\Filament\Pages\Traits\HasLogradouroActions;
use App\Filament\Pages\Traits\HasPosteActions;
use App\Filament\Pages\Traits\HasArvoreActions;
use App\Models\Lote;
use App\Models\Edificacao;
use App\Models\Zona;
use App\Models\Quadra;
use App\Models\Poste;
use App\Models\Arvore;
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
}