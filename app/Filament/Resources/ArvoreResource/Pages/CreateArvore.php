<?php

namespace App\Filament\Resources\ArvoreResource\Pages;

use App\Filament\Resources\ArvoreResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateArvore extends CreateRecord
{
    protected static string $resource = ArvoreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['code'] = (string) Str::uuid();

        // Transforma Lat/Lon digitados em Ponto Geográfico
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