<?php

namespace App\Filament\Resources\TipoRendaResource\Pages;

use App\Filament\Resources\TipoRendaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTipoRendas extends ListRecords
{
    protected static string $resource = TipoRendaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
