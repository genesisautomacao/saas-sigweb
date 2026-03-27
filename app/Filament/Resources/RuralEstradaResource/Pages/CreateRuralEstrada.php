<?php

namespace App\Filament\Resources\RuralEstradaResource\Pages;

use App\Filament\Resources\RuralEstradaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;

class CreateRuralEstrada extends CreateRecord
{
    protected static string $resource = RuralEstradaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['code'] = (string) Str::uuid();

        if (!empty($data['geo_json_input'])) {
            $data['geo'] = json_decode($data['geo_json_input'], true);

            // 🛑 BLINDAGEM TOPOLÓGICA POSTGIS 🛑
            if (!empty($data['rural_localidade_id'])) {
                
                $verificacao = DB::selectOne("
                    SELECT ST_CoveredBy(
                        ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON(?))::geometry, 4326), 
                        (SELECT geo FROM rural_localidades WHERE id = ?)
                    ) as is_inside
                ", [$data['geo_json_input'], $data['rural_localidade_id']]);

                if (!$verificacao->is_inside) {
                    Notification::make()
                        ->title('Erro de Delimitação Geográfica')
                        ->body('O traçado desta Estrada ultrapassa os limites da Localidade/Distrito selecionado.')
                        ->danger()
                        ->send();

                    throw ValidationException::withMessages([
                        'geo_json_input' => 'Traçado fora dos limites da localidade.'
                    ]);
                }
            }
        }
        
        unset($data['geo_json_input']);
        return $data;
    }

    protected function afterCreate(): void
    {
        // 🛑 Calcula a EXTENSÃO LINEAR da estrada (em metros) através do tipo geography
        DB::statement("UPDATE rural_estradas SET extensao_geo = ST_Length(geo::geography) WHERE id = ?", [$this->record->id]);
    }
}