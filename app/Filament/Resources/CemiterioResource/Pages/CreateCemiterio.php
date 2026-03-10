<?php

namespace App\Filament\Resources\CemiterioResource\Pages;

use App\Filament\Resources\CemiterioResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CreateCemiterio extends CreateRecord
{
    protected static string $resource = CemiterioResource::class;

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
        // Força o banco a calcular a área métrica exata logo após criar
        DB::statement("UPDATE cemiterios SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$this->record->id]);
    }
}