<?php

namespace App\Filament\Resources\TipoPosteResource\Pages;

use App\Filament\Resources\TipoPosteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoPoste extends EditRecord
{
    protected static string $resource = TipoPosteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
