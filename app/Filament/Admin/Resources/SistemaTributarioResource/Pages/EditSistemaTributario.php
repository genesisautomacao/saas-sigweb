<?php

namespace App\Filament\Admin\Resources\SistemaTributarioResource\Pages;

use App\Filament\Admin\Resources\SistemaTributarioResource;
use App\Services\Fiscal\MapaFiscalService;
use Filament\Resources\Pages\EditRecord;

class EditSistemaTributario extends EditRecord
{
    protected static string $resource = SistemaTributarioResource::class;

    protected function afterSave(): void
    {
        // As próximas importações/sincronizações já leem o mapa novo
        MapaFiscalService::limparCache();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
