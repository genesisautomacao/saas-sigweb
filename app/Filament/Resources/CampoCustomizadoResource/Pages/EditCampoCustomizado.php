<?php

namespace App\Filament\Resources\CampoCustomizadoResource\Pages;

use App\Filament\Resources\CampoCustomizadoResource;
use App\Services\Coleta\CampoCustomizadoService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCampoCustomizado extends EditRecord
{
    protected static string $resource = CampoCustomizadoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        CampoCustomizadoService::limparCache();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
