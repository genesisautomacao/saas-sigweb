<?php

namespace App\Filament\Resources\MobTipoSinalizacaoResource\Pages;

use App\Filament\Resources\MobTipoSinalizacaoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMobTiposSinalizacao extends ListRecords
{
    protected static string $resource = MobTipoSinalizacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Novo Tipo'),
        ];
    }
}
