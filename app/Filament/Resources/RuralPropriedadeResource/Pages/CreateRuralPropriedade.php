<?php

namespace App\Filament\Resources\RuralPropriedadeResource\Pages;

use App\Filament\Resources\RuralPropriedadeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;

class CreateRuralPropriedade extends CreateRecord
{
    protected static string $resource = RuralPropriedadeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['code'] = (string) Str::uuid();

        if (!empty($data['geo_json_input'])) {
            $data['geo'] = json_decode($data['geo_json_input'], true);

            // 🛑 BLINDAGEM TOPOLÓGICA POSTGIS 🛑
            if (!empty($data['rural_localidade_id'])) {
                
                // Forçamos o ST_SetSRID para 4326 para o PostGIS não dar erro de projeção
                $verificacao = DB::selectOne("
                    SELECT ST_CoveredBy(
                        ST_SetSRID(ST_Multi(ST_GeomFromGeoJSON(?))::geometry, 4326), 
                        (SELECT geo FROM rural_localidades WHERE id = ?)
                    ) as is_inside
                ", [$data['geo_json_input'], $data['rural_localidade_id']]);

                if (!$verificacao->is_inside) {
                    // 1. Mostra o balão vermelho de erro no canto da tela
                    Notification::make()
                        ->title('Erro de Delimitação Geográfica')
                        ->body('O polígono desta Propriedade ultrapassa os limites da Localidade/Distrito selecionado.')
                        ->danger()
                        ->send();

                    // 2. Trava o salvamento e marca o campo de vermelho
                    throw ValidationException::withMessages([
                        'geo_json_input' => 'Polígono fora dos limites da localidade.'
                    ]);
                }
            }
        }
        
        unset($data['geo_json_input']);
        return $data;
    }

    protected function afterCreate(): void
    {
        DB::statement("UPDATE rural_propriedades SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$this->record->id]);
    }
}