<?php

namespace App\Filament\Resources\EmbalagemResource\Pages;

use App\Filament\Resources\EmbalagemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmbalagens extends ListRecords
{
    protected static string $resource = EmbalagemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
