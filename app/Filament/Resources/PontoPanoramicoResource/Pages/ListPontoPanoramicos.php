<?php

namespace App\Filament\Resources\PontoPanoramicoResource\Pages;

use App\Filament\Resources\PontoPanoramicoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPontoPanoramicos extends ListRecords
{
    protected static string $resource = PontoPanoramicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
