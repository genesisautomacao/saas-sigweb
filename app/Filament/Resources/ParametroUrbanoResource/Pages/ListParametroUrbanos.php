<?php

namespace App\Filament\Resources\ParametroUrbanoResource\Pages;

use App\Filament\Resources\ParametroUrbanoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParametroUrbanos extends ListRecords
{
    protected static string $resource = ParametroUrbanoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
