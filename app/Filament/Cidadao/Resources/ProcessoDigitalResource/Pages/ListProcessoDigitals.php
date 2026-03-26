<?php

namespace App\Filament\Cidadao\Resources\ProcessoDigitalResource\Pages;

use App\Filament\Cidadao\Resources\ProcessoDigitalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcessoDigitals extends ListRecords
{
    protected static string $resource = ProcessoDigitalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
