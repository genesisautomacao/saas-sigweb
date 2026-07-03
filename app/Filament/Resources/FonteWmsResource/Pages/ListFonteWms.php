<?php

namespace App\Filament\Resources\FonteWmsResource\Pages;

use App\Filament\Resources\FonteWmsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFonteWms extends ListRecords
{
    protected static string $resource = FonteWmsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
