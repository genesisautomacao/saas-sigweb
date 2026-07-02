<?php

namespace App\Filament\Resources\SetorResource\Pages;

use App\Filament\Resources\SetorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSetores extends ListRecords
{
    protected static string $resource = SetorResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
