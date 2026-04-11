<?php

namespace App\Filament\Resources\ZoneamentoRegraResource\Pages;

use App\Filament\Resources\ZoneamentoRegraResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditZoneamentoRegra extends EditRecord
{
    protected static string $resource = ZoneamentoRegraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
