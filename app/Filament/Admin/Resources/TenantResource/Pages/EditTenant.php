<?php

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Filament\Admin\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Preserva as chaves do JSON `data` que não estão no formulário.
     *
     * O Filament devolve APENAS os caminhos dos campos visíveis, e `tenant.data` é
     * escrito por vários lugares (boletim de coleta, enquadramento salvo no mapa,
     * credenciais das integrações). Sem este merge, salvar a prefeitura apagaria as
     * chaves ausentes — inclusive as seções que ficam ocultas para o Operador.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('data', $data)) {
            $data['data'] = array_merge($this->record->data ?? [], $data['data'] ?? []);
        }

        return $data;
    }
}
