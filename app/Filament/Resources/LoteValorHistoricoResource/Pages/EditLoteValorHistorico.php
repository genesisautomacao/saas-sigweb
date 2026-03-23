<?php

namespace App\Filament\Resources\LoteValorHistoricoResource\Pages;

use App\Filament\Resources\LoteValorHistoricoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoteValorHistorico extends EditRecord
{
    protected static string $resource = LoteValorHistoricoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
