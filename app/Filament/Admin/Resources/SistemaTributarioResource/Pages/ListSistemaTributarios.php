<?php

namespace App\Filament\Admin\Resources\SistemaTributarioResource\Pages;

use App\Filament\Admin\Resources\SistemaTributarioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSistemaTributarios extends ListRecords
{
    protected static string $resource = SistemaTributarioResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Novo Sistema')];
    }
}
