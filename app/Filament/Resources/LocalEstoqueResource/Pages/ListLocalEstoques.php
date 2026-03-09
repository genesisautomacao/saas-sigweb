<?php

namespace App\Filament\Resources\LocalEstoqueResource\Pages;

use App\Filament\Resources\LocalEstoqueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLocalEstoques extends ListRecords
{
    protected static string $resource = LocalEstoqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
