<?php

namespace App\Filament\Resources\ChamadoResource\Pages;

use App\Filament\Resources\ChamadoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChamado extends EditRecord
{
    protected static string $resource = ChamadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $geo = $this->record->geo_json;
        if ($geo && isset($geo->coordinates)) {
            $data['longitude'] = $geo->coordinates[0] ?? null;
            $data['latitude'] = $geo->coordinates[1] ?? null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $lat = $data['latitude'] ?? null;
        $lon = $data['longitude'] ?? null;
        if ($lat !== null && $lon !== null && $lat !== '' && $lon !== '') {
            $data['geo'] = ['type' => 'Point', 'coordinates' => [(float) $lon, (float) $lat]];
        }
        unset($data['latitude'], $data['longitude']);

        return $data;
    }
}
