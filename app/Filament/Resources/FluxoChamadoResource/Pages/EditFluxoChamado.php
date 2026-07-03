<?php

namespace App\Filament\Resources\FluxoChamadoResource\Pages;

use App\Filament\Resources\FluxoChamadoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFluxoChamado extends EditRecord
{
    protected static string $resource = FluxoChamadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
