<?php

namespace App\Filament\Resources\RuralPropriedadeResource\Pages;

use App\Filament\Resources\RuralPropriedadeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;

class EditRuralPropriedade extends EditRecord
{
    protected static string $resource = RuralPropriedadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record->geo_json) {
            $data['geo_json_input'] = json_encode($this->record->geo_json, JSON_PRETTY_PRINT);
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
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
                        ->body('O polígono desta Propriedade ultrapassa os limites da Localidade/Distrito selecionado.')
                        ->danger()
                        ->send();

                    throw ValidationException::withMessages([
                        'geo_json_input' => 'Polígono fora dos limites da localidade.'
                    ]);
                }
            }
        }
        
        unset($data['geo_json_input']);
        return $data;
    }

    protected function afterSave(): void
    {
        DB::statement("UPDATE rural_propriedades SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$this->record->id]);
    }
}