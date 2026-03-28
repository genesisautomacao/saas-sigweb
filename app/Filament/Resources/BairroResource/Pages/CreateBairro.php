<?php

namespace App\Filament\Resources\BairroResource\Pages;

use App\Filament\Resources\BairroResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateBairro extends CreateRecord
{
    protected static string $resource = BairroResource::class;

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