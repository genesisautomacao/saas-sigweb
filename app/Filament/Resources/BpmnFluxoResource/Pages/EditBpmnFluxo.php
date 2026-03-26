<?php

namespace App\Filament\Resources\BpmnFluxoResource\Pages;

use App\Filament\Resources\BpmnFluxoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBpmnFluxo extends EditRecord
{
    protected static string $resource = BpmnFluxoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
