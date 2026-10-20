<?php

namespace App\Filament\Resources\SetorFiscalResource\Pages;

use App\Filament\Resources\Concerns\GeometriaPorTexto;
use App\Filament\Resources\SetorFiscalResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateSetorFiscal extends CreateRecord
{
    use GeometriaPorTexto;

    protected static string $resource = SetorFiscalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->geometriaDoTexto($data);
    }

    protected function afterCreate(): void
    {
        DB::statement('UPDATE setores_fiscais SET area_geo = ST_Area(geo::geography) WHERE id = ? AND geo IS NOT NULL', [$this->record->id]);
    }
}
