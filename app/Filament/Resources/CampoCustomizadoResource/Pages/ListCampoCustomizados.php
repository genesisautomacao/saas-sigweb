<?php

namespace App\Filament\Resources\CampoCustomizadoResource\Pages;

use App\Filament\Resources\CampoCustomizadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCampoCustomizados extends ListRecords
{
    protected static string $resource = CampoCustomizadoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Novo Campo')];
    }
}
