<?php

namespace App\Filament\Resources\LogradouroResource\Pages;

use App\Filament\Resources\LogradouroResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateLogradouro extends CreateRecord
{
    protected static string $resource = LogradouroResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Converte o texto colado do GeoJSON para Array do banco de dados
        if (!empty($data['geo_json_input'])) {
            try {
                $data['geo'] = json_decode($data['geo_json_input'], true);
            } catch (\Exception $e) {
                // Se o JSON for inválido, salva a rua sem geografia
            }
        }
        
        // Limpa o campo virtual para não tentar salvar na coluna inexistente
        unset($data['geo_json_input']);
        
        // Injeta os dados obrigatórios automáticos
        $data['tenant_id'] = \Filament\Facades\Filament::getTenant()->id;
        $data['code'] = (string) Str::uuid();

        return $data;
    }
}