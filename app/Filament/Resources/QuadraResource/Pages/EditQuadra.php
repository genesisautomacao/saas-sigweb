<?php

namespace App\Filament\Resources\QuadraResource\Pages;

use App\Filament\Resources\QuadraResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditQuadra extends EditRecord
{
    protected static string $resource = QuadraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // Quando vai ABRIR A TELA (Preencher os dados)
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record->geo_json) {
            // Garante que o dado seja tratado 100% como Array Associativo
            $geo = is_string($this->record->geo_json) 
                ? json_decode($this->record->geo_json, true) 
                : json_decode(json_encode($this->record->geo_json), true);
            
            // Tenta exibir como lista simples se for um polígono padrão
            if (isset($geo['type']) && $geo['type'] === 'MultiPolygon' && count($geo['coordinates']) === 1 && count($geo['coordinates'][0]) === 1) {
                $linhas = [];
                foreach ($geo['coordinates'][0][0] as $pt) {
                    $linhas[] = "{$pt[0]} {$pt[1]}";
                }
                $data['geo_json_input'] = implode(",\n", $linhas);
            } else {
                $data['geo_json_input'] = json_encode($geo, JSON_PRETTY_PRINT);
            }
        }
        return $data;
    }

    // Quando vai SALVAR NO BANCO
    protected function mutateFormDataBeforeSave(array $data): array
    {
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

    /**
     * MÁGICA: Converte uma lista suja de texto em um MultiPolygon
     */
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
            throw new \Exception("São necessários pelo menos 3 pontos.");
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