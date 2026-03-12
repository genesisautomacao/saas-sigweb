<?php

namespace App\Filament\Resources\LoteamentoResource\Pages;

use App\Filament\Resources\LoteamentoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoteamento extends EditRecord
{
    protected static string $resource = LoteamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
