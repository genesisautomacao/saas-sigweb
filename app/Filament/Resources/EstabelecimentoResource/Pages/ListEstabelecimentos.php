<?php

namespace App\Filament\Resources\EstabelecimentoResource\Pages;

use App\Filament\Resources\EstabelecimentoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEstabelecimentos extends ListRecords
{
    protected static string $resource = EstabelecimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
