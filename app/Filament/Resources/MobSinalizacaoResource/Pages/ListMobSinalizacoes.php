<?php

namespace App\Filament\Resources\MobSinalizacaoResource\Pages;

use App\Filament\Resources\Concerns\TemExportacaoMobilidade;
use App\Filament\Resources\MobSinalizacaoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMobSinalizacoes extends ListRecords
{
    use TemExportacaoMobilidade;

    protected static string $resource = MobSinalizacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->mobExportActionGroup('mob_sinalizacao', ['tipoSinalizacao']),
            Actions\CreateAction::make()->label('Nova Sinalização'),
        ];
    }
}
