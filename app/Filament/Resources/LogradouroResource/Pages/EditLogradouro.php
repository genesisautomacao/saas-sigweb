<?php
namespace App\Filament\Resources\LogradouroResource\Pages;

use App\Filament\Resources\LogradouroResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditLogradouro extends EditRecord
{
    protected static string $resource = LogradouroResource::class;

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
            $geo = is_string($this->record->geo_json) 
                ? json_decode($this->record->geo_json, true) 
                : json_decode(json_encode($this->record->geo_json), true);
            
            // Verifica se é uma linha simples
            if (isset($geo['type']) && $geo['type'] === 'LineString' && isset($geo['coordinates'])) {
                $linhas = [];
                foreach ($geo['coordinates'] as $pt) {
                    $linhas[] = "{$pt[0]} {$pt[1]}";
                }
                $data['geo_json_input'] = implode(",\n", $linhas);
            } 
            // Verifica se é uma Multi-Linha com um único traçado
            elseif (isset($geo['type']) && $geo['type'] === 'MultiLineString' && count($geo['coordinates']) === 1) {
                $linhas = [];
                foreach ($geo['coordinates'][0] as $pt) {
                    $linhas[] = "{$pt[0]} {$pt[1]}";
                }
                $data['geo_json_input'] = implode(",\n", $linhas);
            } 
            // Se for complexo, exibe o JSON
            else {
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
                    $data['geo'] = $this->parseRawLineCoordinates($input);
                }
            } catch (\Exception $e) {
                Notification::make()->title('Erro nas Coordenadas')->body($e->getMessage())->danger()->send();
            }
        }
        unset($data['geo_json_input']);
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

        if (count($coordsArray) < 2) {
            throw new \Exception("São necessários pelo menos 2 pontos para desenhar uma linha.");
        }

        return [
            'type' => 'LineString',
            'coordinates' => $coordsArray
        ];
    }
}