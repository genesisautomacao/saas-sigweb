<?php

namespace App\Filament\Resources\Concerns;

use Filament\Notifications\Notification;

/**
 * Campo `geo_json_input` das telas de polígono: aceita GeoJSON OU a lista
 * "longitude latitude" (uma por linha ou separadas por vírgula) — o mesmo
 * formato do "Criar por Coordenada XY" do mapa. Fecha o anel sozinho.
 */
trait GeometriaPorTexto
{
    protected function geometriaDoTexto(array $data): array
    {
        $input = trim((string) ($data['geo_json_input'] ?? ''));
        unset($data['geo_json_input']);

        if ($input === '') {
            return $data;
        }

        try {
            $data['geo'] = str_starts_with($input, '{')
                ? json_decode($input, true, 512, JSON_THROW_ON_ERROR)
                : $this->poligonoDasCoordenadas($input);
        } catch (\Throwable $e) {
            Notification::make()->title('Erro nas Coordenadas')->body($e->getMessage())->danger()->send();
        }

        return $data;
    }

    /** Texto para o campo: lista simples se for um anel só, senão o GeoJSON formatado. */
    protected function textoDaGeometria($geoJson): ?string
    {
        if (! $geoJson) {
            return null;
        }
        $geo = json_decode(json_encode($geoJson), true);

        if (($geo['type'] ?? null) === 'MultiPolygon' && count($geo['coordinates']) === 1 && count($geo['coordinates'][0]) === 1) {
            return implode(",\n", array_map(fn ($pt) => "{$pt[0]} {$pt[1]}", $geo['coordinates'][0][0]));
        }

        return json_encode($geo, JSON_PRETTY_PRINT);
    }

    private function poligonoDasCoordenadas(string $input): array
    {
        $coords = [];
        foreach (preg_split('/[\n,]+/', $input) as $linha) {
            $partes = explode(' ', trim(preg_replace('/\s+/', ' ', $linha)));
            if (count($partes) >= 2) {
                $coords[] = [
                    (float) preg_replace('/[^0-9\.\-]/', '', $partes[0]),
                    (float) preg_replace('/[^0-9\.\-]/', '', $partes[1]),
                ];
            }
        }

        if (count($coords) < 3) {
            throw new \RuntimeException('São necessários pelo menos 3 pontos para formar um polígono.');
        }
        if ($coords[0] !== end($coords)) {
            $coords[] = $coords[0];
        }

        return ['type' => 'MultiPolygon', 'coordinates' => [[$coords]]];
    }
}
