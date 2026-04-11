<?php

namespace App\Filament\Resources\ZoneamentoRegraResource\Pages;

use App\Filament\Resources\ZoneamentoRegraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListZoneamentoRegras extends ListRecords
{
    protected static string $resource = ZoneamentoRegraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
