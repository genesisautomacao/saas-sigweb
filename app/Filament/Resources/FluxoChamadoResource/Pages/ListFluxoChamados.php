<?php

namespace App\Filament\Resources\FluxoChamadoResource\Pages;

use App\Filament\Resources\FluxoChamadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFluxoChamados extends ListRecords
{
    protected static string $resource = FluxoChamadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
