<?php

namespace App\Filament\Resources\PgvDepreciacaoResource\Pages;

use App\Filament\Resources\PgvDepreciacaoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPgvDepreciacoes extends ListRecords
{
    protected static string $resource = PgvDepreciacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
