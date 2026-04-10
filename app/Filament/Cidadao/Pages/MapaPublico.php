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
            ->label('Filtro Avançado')->icon('heroicon-o-funnel')
            ->modalHeading('Construtor de Consultas')->modalSubmitActionLabel('Pesquisar no Mapa')->modalWidth('md')
            ->form([
                Forms\Components\Select::make('layer')->label('Camada')->options(['lotes' => 'Lotes', 'logradouros' => 'Ruas', 'bairros' => 'Bairros'])->live()->required(),
                Forms\Components\Select::make('field')->label('Campo')->options(fn(Forms\Get $get): array => match ($get('layer')) { 'lotes' => ['area_geo' => 'Área em m²', 'numero_lote' => 'Número do Lote'], default => ['name' => 'Nome'], })->required(),
                Forms\Components\Select::make('operator')->label('Condição')->options(['=' => 'Igual a (=)', '>' => 'Maior que (>)', '<' => 'Menor que (<)', 'LIKE' => 'Contém (LIKE)'])->default('=')->required(),
                Forms\Components\TextInput::make('value')->label('Valor')->required(),
            ])
            ->action(function (array $data) {
                $this->filtroAvancadoAtivo = true;
                $this->dispatch('executar-filtro-avancado', dados: $data);
                \Filament\Notifications\Notification::make()->title('Buscando...')->info()->send();
            });
    }

    public function limparFiltroAvancado()
    {
        $this->filtroAvancadoAtivo = false;
        $this->dispatch('limpar-filtro-avancado'); 
        \Filament\Notifications\Notification::make()->title('Filtros removidos!')->success()->send();
    }
}