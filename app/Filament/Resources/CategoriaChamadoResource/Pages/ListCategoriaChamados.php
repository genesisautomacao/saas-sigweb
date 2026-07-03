<?php

namespace App\Filament\Resources\CategoriaChamadoResource\Pages;

use App\Filament\Resources\CategoriaChamadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategoriaChamados extends ListRecords
{
    protected static string $resource = CategoriaChamadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
