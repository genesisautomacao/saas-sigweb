<?php

namespace App\Filament\Resources\BpmnFluxoResource\Pages;

use App\Filament\Resources\BpmnFluxoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBpmnFluxos extends ListRecords
{
    protected static string $resource = BpmnFluxoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
