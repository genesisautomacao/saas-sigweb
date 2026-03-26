<?php

namespace App\Filament\Cidadao\Pages\Auth;

use Filament\Pages\Auth\Register as BaseRegister;
use Filament\Forms\Components\Select;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

class RegisterCidadao extends BaseRegister
{
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        
                        // O NOSSO CAMPO NOVO AQUI!
                        Select::make('tenant_id')
                            ->label('Selecione sua Cidade / Prefeitura')
                            ->options(Tenant::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->helperText('Esta informação é necessária para vincularmos seus processos à prefeitura correta.'),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function handleRegistration(array $data): Model
    {
        // 1. Isolamos o ID da cidade e removemos do array para o Laravel não tentar salvar direto na tabela 'users'
        $tenantId = $data['tenant_id'];
        unset($data['tenant_id']);
        
        // 2. Criamos o Usuário normalmente (Padrão do Filament)
        $user = parent::handleRegistration($data);
        
        // 3. A MÁGICA DA TABELA PIVOT: Vincula o cidadão à cidade escolhida!
        // OBS: Estou assumindo que a relação no seu model User se chama "tenants()". Se for outro nome (ex: empresas()), é só trocar aqui.
        $user->tenants()->attach($tenantId);
        
        return $user;
    }
}