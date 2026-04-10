<?php

namespace App\Filament\Resources\BairroResource\Pages;

use App\Filament\Resources\BairroResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;

class CreateBairro extends CreateRecord
{
    protected static string $resource = BairroResource::class;

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
        // Quebra a string por vírgulas OU quebras de linha
        $linhas = preg_split('/[\n,]+/', $input);
        $coordsArray = [];
        
        foreach ($linhas as $linha) {
            // Remove espaços extras e tabs
            $linha = trim(preg_replace('/\s+/', ' ', $linha));
            if (empty($linha)) continue;
            
            // Divide entre Longitude e Latitude
            $parts = explode(' ', $linha);
            if (count($parts) >= 2) {
                // Limpa qualquer parêntese ou lixo que venha junto no ctrl+c
                $lon = (float) preg_replace('/[^0-9\.\-]/', '', $parts[0]);
                $lat = (float) preg_replace('/[^0-9\.\-]/', '', $parts[1]);
                $coordsArray[] = [$lon, $lat];
            }
        }

        if (count($coordsArray) < 3) {
            throw new \Exception("São necessários pelo menos 3 pontos para formar um polígono válido.");
        }

        // Regra GIS: O polígono precisa ser fechado. Se o primeiro ponto for diferente do último, nós fechamos.
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