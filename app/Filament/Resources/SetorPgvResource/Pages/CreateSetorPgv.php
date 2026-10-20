<?php

namespace App\Filament\Resources\SetorPgvResource\Pages;

use App\Filament\Resources\SetorPgvResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateSetorPgv extends CreateRecord
{
    protected static string $resource = SetorPgvResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
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