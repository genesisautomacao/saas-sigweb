<?php

namespace App\Filament\Resources\LogradouroResource\Pages;

use App\Filament\Resources\LogradouroResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;

class CreateLogradouro extends CreateRecord
{
    protected static string $resource = LogradouroResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Converte o texto colado para Array do banco de dados
        if (!empty($data['geo_json_input'])) {
            try {
                $input = trim($data['geo_json_input']);
                
                if (str_starts_with($input, '{')) {
                    $data['geo'] = json_decode($input, true);
                } else {
                    $data['geo'] = $this->parseRawLineCoordinates($input);
                }
            } catch (\Exception $e) {
                Notification::make()->title('Erro nas Coordenadas')->body($e->getMessage())->danger()->send();
            }
        }
        
        // Limpa o campo virtual para não tentar salvar na coluna inexistente
        unset($data['geo_json_input']);
        
        // Injeta os dados obrigatórios automáticos
        $data['tenant_id'] = \Filament\Facades\Filament::getTenant()->id;
        $data['code'] = (string) Str::uuid();

        return $data;
    }

    /**
     * MÁGICA PARA LINHAS: Converte uma lista suja de texto em um LineString
     */
    private function parseRawLineCoordinates(string $input): array
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

        // Uma linha precisa de apenas 2 pontos (Início e Fim)
        if (count($coordsArray) < 2) {
            throw new \Exception("São necessários pelo menos 2 pontos para desenhar uma linha (Logradouro).");
        }

        // ATENÇÃO: Ao contrário de polígonos, NÂO repetimos o primeiro ponto no final.

        return [
            'type' => 'LineString',
            'coordinates' => $coordsArray
        ];
    }
}