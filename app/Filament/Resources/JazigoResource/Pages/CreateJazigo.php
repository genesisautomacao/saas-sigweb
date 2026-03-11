<?php

namespace App\Filament\Resources\JazigoResource\Pages;

use App\Filament\Resources\JazigoResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CreateJazigo extends CreateRecord
{
    protected static string $resource = JazigoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['code'] = (string) Str::uuid();

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
        DB::statement("UPDATE jazigos SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$this->record->id]);
    }
}