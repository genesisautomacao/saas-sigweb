<?php

namespace App\Filament\Resources\TipoPatrimonioResource\Pages;

use App\Filament\Resources\TipoPatrimonioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipoPatrimonio extends EditRecord
{
    protected static string $resource = TipoPatrimonioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
