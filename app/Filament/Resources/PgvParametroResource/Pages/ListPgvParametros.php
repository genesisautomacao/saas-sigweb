<?php

namespace App\Filament\Resources\PgvParametroResource\Pages;

use App\Filament\Resources\PgvParametroResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPgvParametros extends ListRecords
{
    protected static string $resource = PgvParametroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
