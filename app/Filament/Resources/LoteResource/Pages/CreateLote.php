<?php

namespace App\Filament\Resources\LoteResource\Pages;

use App\Filament\Resources\LoteResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\UnidadeImobiliaria;

class CreateLote extends CreateRecord
{
    protected static string $resource = LoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['code'] = (string) Str::uuid();

        if (!empty($data['geo_json_input'])) {
            try {
                $data['geo'] = json_decode($data['geo_json_input'], true);
            } catch (\Exception $e) {
                $data['geo'] = null;
            }
        }
        unset($data['geo_json_input']);
        return $data;
    }

    protected function afterCreate(): void
    {
        // 1. Calcula a área exata via PostGIS
        DB::statement("UPDATE lotes SET area_geo = ST_Area(geo::geography) WHERE id = ?", [$this->record->id]);

        // 2. REGRA DE NEGÓCIO: Cria a Unidade Imobiliária "mãe" (O terreno vazio)
        $unidade = UnidadeImobiliaria::create([
            'tenant_id' => $this->record->tenant_id,
            'lote_id' => $this->record->id,
            'code' => (string) Str::uuid(),
            // Pega o ponto central do lote para ser a geometria da unidade base
            'geo' => null // Vamos atualizar o ponto exato pelo banco abaixo
        ]);

        // 3. Atualiza o ponto geográfico da unidade para o centro do Lote no PostGIS
        DB::statement("UPDATE unidade_imobiliarias SET geo = (SELECT ST_PointOnSurface(geo) FROM lotes WHERE id = ?) WHERE id = ?", [$this->record->id, $unidade->id]);
    }
}