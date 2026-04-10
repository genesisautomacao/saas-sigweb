<?php

namespace App\Filament\Resources\LoteResource\Pages;

use App\Filament\Resources\LoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class EditLote extends EditRecord
{
    protected static string $resource = LoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // 1. Tratamento do GeoJSON Inteligente
        if ($this->record->geo_json) {
            $geo = is_string($this->record->geo_json) 
                ? json_decode($this->record->geo_json, true) 
                : json_decode(json_encode($this->record->geo_json), true);
            
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

        // 2. 🛑 SMART AUTO-SYNC: Sincroniza as unidades filhas automaticamente se estiverem vazias
        $apiService = app(\App\Services\ApiTools\IntegraPrefeituraService::class);
        
        foreach ($this->record->unidadesImobiliarias as $unidade) {
            // Só faz o auto-sync se tiver o código, mas o JSON ainda estiver vazio
            if ($unidade->codigo_imovel_tributario && empty($unidade->dados_tributarios)) {
                try {
                    $dadosPrefeitura = $apiService->buscarImovelPorCodigo($unidade->codigo_imovel_tributario, $unidade->tenant_id);
                    if ($dadosPrefeitura) {
                        $unidade->update([
                            'inscricao_imobiliaria' => $dadosPrefeitura['inscricao_imobiliaria'],
                            'dados_tributarios' => $dadosPrefeitura 
                        ]);
                    }
                } catch (\Exception $e) {
                    // Falha silenciosa para não quebrar a abertura da tela
                }
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
            throw new \Exception("São necessários pelo menos 3 pontos para formar um polígono válido.");
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

    protected function afterSave(): void
    {
        // 1. Recalcula a Área do Lote
        DB::statement("UPDATE lotes SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$this->record->id]);
        
        // 2. Reposiciona o Ponto Central da Unidade Imobiliária "mãe" para acompanhar o novo Lote
        DB::statement("UPDATE unidade_imobiliarias SET geo = (SELECT ST_PointOnSurface(geo) FROM lotes WHERE id = ?) WHERE lote_id = ?", [$this->record->id, $this->record->id]);
    }
}