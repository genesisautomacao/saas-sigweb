<?php

namespace App\Filament\Resources\CemiterioResource\Pages;

use App\Filament\Resources\CemiterioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditCemiterio extends EditRecord
{
    protected static string $resource = CemiterioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Se a entidade tem geometria, transforma num texto legível pro usuário poder editar
        if ($this->record->geo_json) {
            $data['geo_json_input'] = json_encode($this->record->geo_json, JSON_PRETTY_PRINT);
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['geo_json_input'])) {
            try {
                $data['geo'] = json_decode($data['geo_json_input'], true);
            } catch (\Exception $e) {
                // Silencia se o JSON for inválido
            }
        }
        unset($data['geo_json_input']);
        return $data;
    }

    protected function afterSave(): void
    {
        // Atualiza a área se a geometria mudou
        DB::statement("UPDATE cemiterios SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$this->record->id]);
    }
}