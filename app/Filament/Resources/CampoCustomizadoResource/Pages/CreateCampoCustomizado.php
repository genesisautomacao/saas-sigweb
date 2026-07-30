<?php

namespace App\Filament\Resources\CampoCustomizadoResource\Pages;

use App\Filament\Resources\CampoCustomizadoResource;
use App\Services\Coleta\CampoCustomizadoService;
use Filament\Resources\Pages\CreateRecord;

class CreateCampoCustomizado extends CreateRecord
{
    protected static string $resource = CampoCustomizadoResource::class;

    protected function afterCreate(): void
    {
        CampoCustomizadoService::limparCache();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
