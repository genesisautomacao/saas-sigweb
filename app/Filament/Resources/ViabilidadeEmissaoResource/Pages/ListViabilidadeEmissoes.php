<?php

namespace App\Filament\Resources\ViabilidadeEmissaoResource\Pages;

use App\Filament\Resources\ViabilidadeEmissaoResource;
use Filament\Resources\Pages\ListRecords;

class ListViabilidadeEmissoes extends ListRecords
{
    protected static string $resource = ViabilidadeEmissaoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
