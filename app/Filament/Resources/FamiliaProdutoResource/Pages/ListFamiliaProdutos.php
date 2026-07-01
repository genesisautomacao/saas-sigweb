<?php

namespace App\Filament\Resources\FamiliaProdutoResource\Pages;

use App\Filament\Resources\FamiliaProdutoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFamiliaProdutos extends ListRecords
{
    protected static string $resource = FamiliaProdutoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
