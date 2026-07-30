<?php

namespace App\Filament\Admin\Resources\UsuarioAdminResource\Pages;

use App\Filament\Admin\Resources\UsuarioAdminResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUsuarioAdmin extends CreateRecord
{
    protected static string $resource = UsuarioAdminResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Usuário de estrutura do SaaS: não é cidadão e já entra com o e-mail confirmado.
        $data['tipo'] = 'prefeitura';
        $data['email_verified_at'] = now();

        return $data;
    }

    /**
     * O papel e as capacidades não são colunas do usuário — vêm do estado bruto do
     * formulário (os campos são ->dehydrated(false)) e viram vínculos globais do Spatie.
     */
    protected function afterCreate(): void
    {
        $this->record->sincronizarAcessoAdmin(
            $this->data['papel_admin'] ?? User::PAPEL_OPERADOR,
            $this->data['capacidades'] ?? [],
        );
    }
}
