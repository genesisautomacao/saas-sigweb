<?php

namespace App\Filament\Resources\LogradouroCemiterioResource\Pages;

use App\Filament\Resources\LogradouroCemiterioResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateLogradouroCemiterio extends CreateRecord
{
    protected static string $resource = LogradouroCemiterioResource::class;

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
}