<?php

namespace App\Filament\Resources\TipoEntidadeResource\Pages;

use App\Filament\Resources\TipoEntidadeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTipoEntidades extends ListRecords
{
    protected static string $resource = TipoEntidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
