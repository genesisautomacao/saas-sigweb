<?php

namespace App\Filament\Resources\PosteResource\Pages;

use App\Filament\Resources\PosteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPoste extends EditRecord
{
    protected static string $resource = PosteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // Quando abre a tela: Pega a geometria do banco e separa em Lat / Lon
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record->geo_json) {
            $data['longitude'] = $this->record->geo_json->coordinates[0] ?? null;
            $data['latitude'] = $this->record->geo_json->coordinates[1] ?? null;
        }

        return $data;
    }

    // Quando clica em salvar: Junta Lat / Lon num GeoJSON de Ponto
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['latitude']) && !empty($data['longitude'])) {
            $data['geo'] = [
                'type' => 'Point',
                'coordinates' => [(float) $data['longitude'], (float) $data['latitude']]
            ];
        }

        unset($data['latitude'], $data['longitude']);

        return $data;
    }
}