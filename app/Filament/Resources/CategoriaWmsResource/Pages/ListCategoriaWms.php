<?php

namespace App\Filament\Resources\CategoriaWmsResource\Pages;

use App\Filament\Resources\CategoriaWmsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategoriaWms extends ListRecords
{
    protected static string $resource = CategoriaWmsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
