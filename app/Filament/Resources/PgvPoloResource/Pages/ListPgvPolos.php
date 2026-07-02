<?php

namespace App\Filament\Resources\PgvPoloResource\Pages;

use App\Filament\Resources\PgvPoloResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPgvPolos extends ListRecords
{
    protected static string $resource = PgvPoloResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
