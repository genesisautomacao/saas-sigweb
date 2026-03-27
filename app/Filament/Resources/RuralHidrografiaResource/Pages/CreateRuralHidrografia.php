<?php

namespace App\Filament\Resources\RuralHidrografiaResource\Pages;

use App\Filament\Resources\RuralHidrografiaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;

class CreateRuralHidrografia extends CreateRecord
{
    protected static string $resource = RuralHidrografiaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['code'] = (string) Str::uuid();

        if (!empty($data['geo_json_input'])) {
            $data['geo'] = json_decode($data['geo_json_input'], true);

            // 🛑 BLINDAGEM TOPOLÓGICA POSTGIS (ST_Intersects para Rios) 🛑
            if (!empty($data['rural_localidade_id'])) {
                
                // ST_Intersects: Apenas valida se as águas passam por dentro do Distrito
                $verificacao = DB::selectOne("
                    SELECT ST_Intersects(
                        ST_SetSRID(ST_GeomFromGeoJSON(?)::geometry, 4326), 
                        (SELECT geo FROM rural_localidades WHERE id = ?)
                    ) as cruza_localidade
                ", [$data['geo_json_input'], $data['rural_localidade_id']]);

                if (!$verificacao->cruza_localidade) {
                    Notification::make()
                        ->title('Erro de Delimitação Geográfica')
                        ->body('Esta Hidrografia não passa pelos limites da Localidade selecionada.')
                        ->danger()
                        ->send();

                    throw ValidationException::withMessages([
                        'geo_json_input' => 'Geometria não cruza a localidade referenciada.'
                    ]);
                }
            }
        }
        
        unset($data['geo_json_input']);
        return $data;
    }
}