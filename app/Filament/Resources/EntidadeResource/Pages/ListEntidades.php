<?php

namespace App\Filament\Resources\EntidadeResource\Pages;

use App\Filament\Resources\EntidadeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEntidades extends ListRecords
{
    protected static string $resource = EntidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
