<?php

namespace App\Filament\Resources\AreaReurbResource\Pages;

use App\Filament\Resources\AreaReurbResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAreasReurb extends ListRecords
{
    protected static string $resource = AreaReurbResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nova Área REURB'),
        ];
    }
}
