<?php

namespace App\Filament\Resources\TipoEstoqueResource\Pages;

use App\Filament\Resources\TipoEstoqueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTipoEstoques extends ListRecords
{
    protected static string $resource = TipoEstoqueResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
