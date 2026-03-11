<?php

namespace App\Filament\Resources\QuadraCemiterioResource\Pages;

use App\Filament\Resources\QuadraCemiterioResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CreateQuadraCemiterio extends CreateRecord
{
    protected static string $resource = QuadraCemiterioResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['code'] = (string) Str::uuid();

        // Transforma a String GeoJSON colada em Array para a Model entender
        if (!empty($data['geo_json_input'])) {
            try {
                $data['geo'] = json_decode($data['geo_json_input'], true);
            } catch (\Exception $e) {
                $data['geo'] = null;
            }
        }
        unset($data['geo_json_input']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Força o cálculo de área exata logo após criar via texto
        DB::statement("UPDATE quadras_cemiterio SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$this->record->id]);
    }
}