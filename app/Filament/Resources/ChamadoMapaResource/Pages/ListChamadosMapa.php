<?php

namespace App\Filament\Resources\ChamadoMapaResource\Pages;

use App\Filament\Resources\ChamadoMapaResource;
use Filament\Resources\Pages\ListRecords;

class ListChamadosMapa extends ListRecords
{
    protected static string $resource = ChamadoMapaResource::class;

    protected ?string $subheading = 'Chamados abertos pelos cidadãos no botão "Fale conosco" do mapa público.';
}
