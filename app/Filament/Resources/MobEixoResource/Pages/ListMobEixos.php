<?php

namespace App\Filament\Resources\MobEixoResource\Pages;

use App\Filament\Resources\Concerns\TemExportacaoMobilidade;
use App\Filament\Resources\MobEixoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMobEixos extends ListRecords
{
    use TemExportacaoMobilidade;

    protected static string $resource = MobEixoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->mobExportActionGroup('mob_eixo'),
            Actions\CreateAction::make()->label('Novo Eixo'),
        ];
    }
}
