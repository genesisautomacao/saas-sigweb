<?php

namespace App\Filament\Resources\PgvAmostraResource\Pages;

use App\Filament\Resources\PgvAmostraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPgvAmostras extends ListRecords
{
    protected static string $resource = PgvAmostraResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
