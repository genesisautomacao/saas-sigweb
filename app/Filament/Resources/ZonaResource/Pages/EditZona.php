<?php

namespace App\Filament\Resources\ZonaResource\Pages;

use App\Filament\Resources\ZonaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditZona extends EditRecord
{
    protected static string $resource = ZonaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record->geo_json) {
            $geo = is_string($this->record->geo_json) 
                ? json_decode($this->record->geo_json, true) 
                : json_decode(json_encode($this->record->geo_json), true);
            
            // CORRIGIDO: Lê tanto Polygon (antigo) quanto MultiPolygon (novo)
            if (isset($geo['type']) && $geo['type'] === 'Polygon' && isset($geo['coordinates'][0])) {
                $linhas = [];
                foreach ($geo['coordinates'][0] as $pt) {
                    $linhas[] = "{$pt[0]} {$pt[1]}";
                }
                $data['geo_json_input'] = implode(",\n", $linhas);
            } 
            elseif (isset($geo['type']) && $geo['type'] === 'MultiPolygon' && isset($geo['coordinates'][0][0])) {
                $linhas = [];
                foreach ($geo['coordinates'][0][0] as $pt) {
                    $linhas[] = "{$pt[0]} {$pt[1]}";
                }
                $data['geo_json_input'] = implode(",\n", $linhas);
            } 
            else {
                // Cai aqui só se for um monstro cheio de "buracos" no meio
                $data['geo_json_input'] = json_encode($geo, JSON_PRETTY_PRINT);
            }
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['rgb'])) {
            $data['rgb'] = str_replace(['rgb(', ')', ' '], '', $data['rgb']);
        }

        if (!empty($data['geo_json_input'])) {
            try {
                $input = trim($data['geo_json_input']);
                if (str_starts_with($input, '{')) {
                    $data['geo'] = json_decode($input, true);
                } else {
                    $data['geo'] = $this->parseRawCoordinates($input);
                }
            } catch (\Exception $e) {
                Notification::make()->title('Erro nas Coordenadas')->body($e->getMessage())->danger()->send();
            }
        }
        
        unset($data['geo_json_input']);
        return $data;
    }

    private function parseRawCoordinates(string $input): array
    {
        $linhas = preg_split('/[\n,]+/', $input);
        $coordsArray = [];
        
        foreach ($linhas as $linha) {
            $linha = trim(preg_replace('/\s+/', ' ', $linha));
            if (empty($linha)) continue;
            
            $parts = explode(' ', $linha);
            if (count($parts) >= 2) {
                $lon = (float) preg_replace('/[^0-9\.\-]/', '', $parts[0]);
                $lat = (float) preg_replace('/[^0-9\.\-]/', '', $parts[1]);
                $coordsArray[] = [$lon, $lat];
            }
        }

        if (count($coordsArray) < 3) {
            throw new \Exception("São necessários pelo menos 3 pontos para fechar o polígono.");
        }

        $first = $coordsArray[0];
        $last = end($coordsArray);
        if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
            $coordsArray[] = $first;
        }

        return [
            'type' => 'MultiPolygon',
            'coordinates' => [[$coordsArray]]
        ];
    }
}