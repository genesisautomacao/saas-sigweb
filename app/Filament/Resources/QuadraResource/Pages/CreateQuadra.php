<?php

namespace App\Filament\Resources\QuadraResource\Pages;

use App\Filament\Resources\QuadraResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;

class CreateQuadra extends CreateRecord
{
    protected static string $resource = QuadraResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['code'] = (string) Str::uuid();

        if (!empty($data['geo_json_input'])) {
            try {
                $input = trim($data['geo_json_input']);
                
                // Se começar com '{', é um GeoJSON padrão
                if (str_starts_with($input, '{')) {
                    $data['geo'] = json_decode($input, true);
                } else {
                    // Se não, joga pro nosso conversor inteligente
                    $data['geo'] = $this->parseRawCoordinates($input);
                }
            } catch (\Exception $e) {
                $data['geo'] = null;
                Notification::make()->title('Erro nas Coordenadas')->body($e->getMessage())->danger()->send();
            }
        }
        
        unset($data['geo_json_input']);
        return $data;
    }

    /**
     * MÁGICA: Converte uma lista suja de texto em um MultiPolygon perfeito
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
            throw new \Exception("São necessários pelo menos 3 pontos para formar um polígono válido.");
        }

        // Regra GIS: O polígono precisa ser fechado.
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