<?php

namespace App\Filament\Resources\PgvCubResource\Pages;

use App\Filament\Resources\PgvCubResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPgvCubs extends ListRecords
{
    protected static string $resource = PgvCubResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
