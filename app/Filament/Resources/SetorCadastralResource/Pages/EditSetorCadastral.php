<?php

namespace App\Filament\Resources\SetorCadastralResource\Pages;

use App\Filament\Resources\Concerns\GeometriaPorTexto;
use App\Filament\Resources\SetorCadastralResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditSetorCadastral extends EditRecord
{
    use GeometriaPorTexto;

    protected static string $resource = SetorCadastralResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['geo_json_input'] = $this->textoDaGeometria($this->record->geo_json);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->geometriaDoTexto($data);
    }

    protected function afterSave(): void
    {
        DB::statement('UPDATE setores_cadastrais SET area_geo = ST_Area(geo::geography) WHERE id = ? AND geo IS NOT NULL', [$this->record->id]);
    }
}
