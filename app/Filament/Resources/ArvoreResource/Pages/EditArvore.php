<?php

namespace App\Filament\Resources\ArvoreResource\Pages;

use App\Filament\Resources\ArvoreResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArvore extends EditRecord
{
    protected static string $resource = ArvoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record->geo_json) {
            $data['longitude'] = $this->record->geo_json->coordinates[0] ?? null;
            $data['latitude'] = $this->record->geo_json->coordinates[1] ?? null;
        }
        return $data;
    }

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