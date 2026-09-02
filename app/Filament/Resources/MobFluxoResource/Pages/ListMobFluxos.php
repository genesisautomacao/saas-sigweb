<?php

namespace App\Filament\Resources\MobFluxoResource\Pages;

use App\Filament\Resources\Concerns\TemExportacaoMobilidade;
use App\Filament\Resources\MobFluxoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMobFluxos extends ListRecords
{
    use TemExportacaoMobilidade;

    protected static string $resource = MobFluxoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->mobExportActionGroup('mob_fluxo'),
            Actions\CreateAction::make()->label('Novo Fluxo'),
        ];
    }
}
