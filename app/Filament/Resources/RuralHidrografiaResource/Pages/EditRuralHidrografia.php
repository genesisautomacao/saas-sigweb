<?php

namespace App\Filament\Resources\RuralHidrografiaResource\Pages;

use App\Filament\Resources\RuralHidrografiaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;

class EditRuralHidrografia extends EditRecord
{
    protected static string $resource = RuralHidrografiaResource::class;

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

            // 🛑 BLINDAGEM TOPOLÓGICA POSTGIS (ST_Intersects) 🛑
            if (!empty($data['rural_localidade_id'])) {
                
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