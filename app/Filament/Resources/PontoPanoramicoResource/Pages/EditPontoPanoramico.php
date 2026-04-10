<?php

namespace App\Filament\Resources\PontoPanoramicoResource\Pages;

use App\Filament\Resources\PontoPanoramicoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPontoPanoramico extends EditRecord
{
    protected static string $resource = PontoPanoramicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
