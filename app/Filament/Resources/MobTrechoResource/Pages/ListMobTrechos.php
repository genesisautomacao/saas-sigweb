<?php

namespace App\Filament\Resources\MobTrechoResource\Pages;

use App\Filament\Resources\Concerns\TemExportacaoMobilidade;
use App\Filament\Resources\MobTrechoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMobTrechos extends ListRecords
{
    use TemExportacaoMobilidade;

    protected static string $resource = MobTrechoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->mobExportActionGroup('mob_trecho', ['logradouro']),
            Actions\CreateAction::make()->label('Novo Trecho'),
        ];
    }
}
