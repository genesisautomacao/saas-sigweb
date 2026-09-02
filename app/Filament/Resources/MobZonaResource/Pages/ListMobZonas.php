<?php

namespace App\Filament\Resources\MobZonaResource\Pages;

use App\Filament\Resources\Concerns\TemExportacaoMobilidade;
use App\Filament\Resources\MobZonaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMobZonas extends ListRecords
{
    use TemExportacaoMobilidade;

    protected static string $resource = MobZonaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->mobExportActionGroup('mob_zona'),
            Actions\CreateAction::make()->label('Nova Zona'),
        ];
    }
}
