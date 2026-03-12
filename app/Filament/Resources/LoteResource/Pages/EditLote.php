<?php

namespace App\Filament\Resources\LoteResource\Pages;

use App\Filament\Resources\LoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

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
        // 1. Tratamento do GeoJSON que já tínhamos
        if ($this->record->geo_json) {
            $data['geo_json_input'] = json_encode($this->record->geo_json, JSON_PRETTY_PRINT);
        }

        // 2. 🛑 SMART AUTO-SYNC: Sincroniza as unidades filhas automaticamente se estiverem vazias
        $apiService = app(\App\Services\ApiTools\IntegraPrefeituraService::class);
        
        foreach ($this->record->unidadesImobiliarias as $unidade) {
            // Só faz o auto-sync se tiver o código, mas o JSON ainda estiver vazio
            if ($unidade->codigo_imovel_tributario && empty($unidade->dados_tributarios)) {
                try {
                    $dadosPrefeitura = $apiService->buscarImovelPorCodigo($unidade->codigo_imovel_tributario);
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
                $data['geo'] = json_decode($data['geo_json_input'], true);
            } catch (\Exception $e) {
                // Se o JSON for inválido, ignora a alteração geográfica
            }
        }
        unset($data['geo_json_input']);
        return $data;
    }

    protected function afterSave(): void
    {
        // 1. Recalcula a Área do Lote
        DB::statement("UPDATE lotes SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$this->record->id]);
        
        // 2. Reposiciona o Ponto Central da Unidade Imobiliária "mãe" para acompanhar o novo Lote
        DB::statement("UPDATE unidade_imobiliarias SET geo = (SELECT ST_PointOnSurface(geo) FROM lotes WHERE id = ?) WHERE lote_id = ?", [$this->record->id, $this->record->id]);
    }
}