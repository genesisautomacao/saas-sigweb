<?php

namespace App\Filament\Resources\MeioFioResource\Pages;

use App\Filament\Resources\MeioFioResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMeioFio extends EditRecord
{
    protected static string $resource = MeioFioResource::class;

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

            // LineString simples → lista crua "lon lat"
            if (isset($geo['type']) && $geo['type'] === 'LineString') {
                $linhas = [];
                foreach ($geo['coordinates'] as $pt) {
                    $linhas[] = "{$pt[0]} {$pt[1]}";
                }
                $data['geo_json_input'] = implode(",\n", $linhas);
            } elseif (isset($geo['type']) && $geo['type'] === 'MultiLineString' && count($geo['coordinates']) === 1) {
                $linhas = [];
                foreach ($geo['coordinates'][0] as $pt) {
                    $linhas[] = "{$pt[0]} {$pt[1]}";
                }
                $data['geo_json_input'] = implode(",\n", $linhas);
            } else {
                $data['geo_json_input'] = json_encode($geo, JSON_PRETTY_PRINT);
            }
        }
        return $data;
    }

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

    private function parseRawCoordinates(string $input): array
    {
        $linhas = preg_split('/[\n,]+/', $input);
        $coords = [];
        foreach ($linhas as $linha) {
            $linha = trim(preg_replace('/\s+/', ' ', $linha));
            if (empty($linha)) continue;
            $parts = explode(' ', $linha);
            if (count($parts) >= 2) {
                $lon = (float) preg_replace('/[^0-9\.\-]/', '', $parts[0]);
                $lat = (float) preg_replace('/[^0-9\.\-]/', '', $parts[1]);
                $coords[] = [$lon, $lat];
            }
        }
        if (count($coords) < 2) {
            throw new \Exception('São necessários pelo menos 2 pontos.');
        }
        return [
            'type'        => 'LineString',
            'coordinates' => $coords,
        ];
    }
}
