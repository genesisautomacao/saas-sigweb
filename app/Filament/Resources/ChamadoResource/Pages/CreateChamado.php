<?php

namespace App\Filament\Resources\ChamadoResource\Pages;

use App\Filament\Resources\ChamadoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChamado extends CreateRecord
{
    protected static string $resource = ChamadoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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
