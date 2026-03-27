<?php

namespace App\Filament\Resources\RuralPonteResource\Pages;

use App\Filament\Resources\RuralPonteResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;

class CreateRuralPonte extends CreateRecord
{
    protected static string $resource = RuralPonteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['code'] = (string) Str::uuid();

        if (!empty($data['geo_json_input'])) {
            $data['geo'] = json_decode($data['geo_json_input'], true);

            // 🛑 BLINDAGEM TOPOLÓGICA POSTGIS 🛑
            // A coordenada da ponte (Ponto) deve estar contida dentro da Localidade
            if (!empty($data['rural_localidade_id'])) {
                
                $verificacao = DB::selectOne("
                    SELECT ST_Intersects(
                        ST_SetSRID(ST_GeomFromGeoJSON(?)::geometry, 4326), 
                        (SELECT geo FROM rural_localidades WHERE id = ?)
                    ) as cruza_localidade
                ", [$data['geo_json_input'], $data['rural_localidade_id']]);

                if (!$verificacao->cruza_localidade) {
                    Notification::make()
                        ->title('Erro de Localização Geográfica')
                        ->body('A coordenada informada para esta Ponte encontra-se fora dos limites da Localidade/Distrito selecionado.')
                        ->danger()
                        ->send();

                    throw ValidationException::withMessages([
                        'geo_json_input' => 'O ponto geográfico não pertence à localidade referenciada.'
                    ]);
                }
            }
        }
        
        unset($data['geo_json_input']);
        return $data;
    }
}