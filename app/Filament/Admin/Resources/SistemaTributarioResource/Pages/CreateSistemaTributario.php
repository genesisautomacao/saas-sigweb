<?php

namespace App\Filament\Admin\Resources\SistemaTributarioResource\Pages;

use App\Filament\Admin\Resources\SistemaTributarioResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSistemaTributario extends CreateRecord
{
    protected static string $resource = SistemaTributarioResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
