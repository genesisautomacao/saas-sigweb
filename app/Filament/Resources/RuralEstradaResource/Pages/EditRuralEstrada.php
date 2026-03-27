<?php

namespace App\Filament\Resources\RuralEstradaResource\Pages;

use App\Filament\Resources\RuralEstradaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;

class EditRuralEstrada extends EditRecord
{
    protected static string $resource = RuralEstradaResource::class;

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

    protected function afterSave(): void
    {
        // 🛑 Recalcula a EXTENSÃO LINEAR da estrada após edição
        DB::statement("UPDATE rural_estradas SET extensao_geo = ST_Length(geo::geography) WHERE id = ?", [$this->record->id]);
    }
}