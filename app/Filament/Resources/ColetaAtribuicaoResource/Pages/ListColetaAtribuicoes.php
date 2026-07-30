<?php

namespace App\Filament\Resources\ColetaAtribuicaoResource\Pages;

use App\Filament\Resources\ColetaAtribuicaoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListColetaAtribuicoes extends ListRecords
{
    protected static string $resource = ColetaAtribuicaoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nova Atribuição')];
    }

    /** R67-4 — mapa geral: onde cada cadastrador está atuando hoje. */
    protected function getHeaderWidgets(): array
    {
        return [\App\Filament\Resources\ColetaAtribuicaoResource\Widgets\MapaRegioesColetaWidget::class];
    }
}
