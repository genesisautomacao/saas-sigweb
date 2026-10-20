<?php

namespace App\Filament\Resources\SetorCadastralResource\Pages;

use App\Filament\Resources\Concerns\GeometriaPorTexto;
use App\Filament\Resources\SetorCadastralResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateSetorCadastral extends CreateRecord
{
    use GeometriaPorTexto;

    protected static string $resource = SetorCadastralResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->geometriaDoTexto($data);
    }

    protected function afterCreate(): void
    {
        DB::statement('UPDATE setores_cadastrais SET area_geo = ST_Area(geo::geography) WHERE id = ? AND geo IS NOT NULL', [$this->record->id]);
    }
}
