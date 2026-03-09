<?php

namespace App\Filament\Resources\PosteResource\Pages;

use App\Filament\Resources\PosteResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePoste extends CreateRecord
{
    protected static string $resource = PosteResource::class;

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

        // Remove as variáveis soltas para não dar erro na query SQL
        unset($data['latitude'], $data['longitude']);

        return $data;
    }
}