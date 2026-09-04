<?php

namespace App\Filament\Resources\MobViaResource\Pages;

use App\Filament\Resources\Concerns\TemExportacaoMobilidade;
use App\Filament\Resources\MobViaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMobVias extends ListRecords
{
    use TemExportacaoMobilidade;

    protected static string $resource = MobViaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->mobExportActionGroup('mob_via', ['logradouro']),
            Actions\CreateAction::make()->label('Nova Via'),
        ];
    }
}
