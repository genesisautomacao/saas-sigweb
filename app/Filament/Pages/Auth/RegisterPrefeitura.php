<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Register as BaseRegister;
use App\Filament\Concerns\HasTenantFirstRegistration;
use Illuminate\Database\Eloquent\Model;

/**
 * Auto-cadastro de usuário da PREFEITURA no painel `app` (item 011 / B12).
 * O usuário é criado SEM papel (aguarda o Gerente definir o acesso). Marca tipo='prefeitura'.
 *
 * Fluxo em 2 passos (ver HasTenantFirstRegistration): escolhe a prefeitura primeiro
 * (`/app/register`) e cai nos dados com a prefeitura fixada (`/app/register?prefeitura={slug}`).
 */
class RegisterPrefeitura extends BaseRegister
{
    use HasTenantFirstRegistration;

    protected function getForms(): array
    {
        $schema = $this->prefeituraDefinida()
            ? [
                $this->faixaPrefeituraSelecionada(),
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]
            : $this->passoSelecaoPrefeitura();

        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema($schema)
                    ->statePath('data'),
            ),
        ];
    }

    protected function handleRegistration(array $data): Model
    {
        // A prefeitura vem da URL (passo 1), não do formulário.
        $tenantId = $this->tenantSelecionadoId;

        // Usuário da prefeitura, sem papel — o Gerente promove depois atribuindo um papel.
        $data['tipo'] = 'prefeitura';

        $user = parent::handleRegistration($data);
        $user->tenants()->attach($tenantId);

        return $user;
    }
}
