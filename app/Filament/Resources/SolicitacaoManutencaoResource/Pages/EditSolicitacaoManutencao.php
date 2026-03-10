<?php

namespace App\Filament\Resources\SolicitacaoManutencaoResource\Pages;

use App\Filament\Resources\SolicitacaoManutencaoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSolicitacaoManutencao extends EditRecord
{
    protected static string $resource = SolicitacaoManutencaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
