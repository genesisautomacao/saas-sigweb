<?php

namespace App\Filament\Resources\ParametroUrbanoResource\Pages;

use App\Filament\Resources\ParametroUrbanoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParametroUrbano extends EditRecord
{
    protected static string $resource = ParametroUrbanoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
