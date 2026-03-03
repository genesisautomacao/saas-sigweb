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
        }
    }

    // Escuta o evento disparado pelo JavaScript
    #[On('abrirFichaImovel')]
    public function carregarFicha($loteId, $loteNome = 'S/N')
    {
        $this->loteAtivoId = $loteId;
        $this->loteAtivoNome = $loteNome;
        $this->showFicha = true;
    }

    public function fecharFicha()
    {
        $this->showFicha = false;
        $this->loteAtivoId = null;
    }

    public function mostrarErro(string $mensagem): void
    {
        Notification::make()
            ->title('Atenção')
            ->body($mensagem)
            ->warning() // Cria um alerta amarelo bonito do Filament
            ->send();
    }
}