<?php

namespace App\Filament\Resources\OperacaoInternaResource\Pages;

use App\Filament\Resources\OperacaoInternaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOperacaoInternas extends ListRecords
{
    protected static string $resource = OperacaoInternaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
