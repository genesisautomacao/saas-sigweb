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

    /**
     * Módulos alterados (D7 — docs/Modulos_Permissoes.txt): os papéis que
     * "acompanham todos os módulos" já foram sincronizados pelo Tenant::updated;
     * os demais precisam receber as permissões do módulo novo na tela de Papéis —
     * avisa quem são, para ninguém descobrir pelo usuário reclamando.
     */
    protected function afterSave(): void
    {
        if (! $this->record->wasChanged('modules')) {
            return;
        }

        $semFlag = \App\Models\Role::where('tenant_id', $this->record->id)
            ->where('todos_modulos', false)
            ->orderBy('name')
            ->pluck('name');

        if ($semFlag->isEmpty()) {
            return;
        }

        \Filament\Notifications\Notification::make()
            ->warning()
            ->persistent()
            ->title('Módulos alterados — confira os papéis da prefeitura')
            ->body('Papéis que NÃO acompanham todos os módulos e precisam receber as permissões novas na tela de Papéis (/app → Configurações → Papéis): '
                .$semFlag->implode(', ').'. Os papéis marcados como "acompanha todos os módulos" já foram atualizados.')
            ->send();
    }
}
