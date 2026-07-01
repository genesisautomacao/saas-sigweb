<?php

namespace App\Filament\Resources\UnidadeMedidaResource\Pages;

use App\Filament\Resources\UnidadeMedidaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUnidadeMedidas extends ListRecords
{
    protected static string $resource = UnidadeMedidaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
