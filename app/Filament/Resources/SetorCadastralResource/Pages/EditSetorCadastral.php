<?php

namespace App\Filament\Resources\SetorCadastralResource\Pages;

use App\Filament\Resources\Concerns\GeometriaPorTexto;
use App\Filament\Resources\SetorCadastralResource;
use App\Models\SetorCadastral;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
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
        $data = $this->geometriaDoTexto($data);
        if (empty($data['geo'])) {
            return $data;
        }

        // B10 — a divisa invadida é recortada; desenho que engole/cai dentro de vizinho é recusado
        $recorte = SetorCadastral::recortarPelosVizinhos($data['geo'], (int) Filament::getTenant()->id, $this->record->id);
        if ($recorte['erro']) {
            Notification::make()->title('Geometria recusada')->body($recorte['erro'])->danger()->persistent()->send();
            $this->halt();
        }

        if ($aviso = SetorCadastral::avisoRecorte($recorte)) {
            Notification::make()->title('Divisa ajustada')->body($aviso)->info()->send();
        }
        $data['geo'] = $recorte['geo'];

        return $data;
    }

    protected function afterSave(): void
    {
        DB::statement('UPDATE setores_cadastrais SET area_geo = ST_Area(geo::geography) WHERE id = ? AND geo IS NOT NULL', [$this->record->id]);
    }
}
