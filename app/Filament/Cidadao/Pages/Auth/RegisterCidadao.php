<?php

namespace App\Filament\Cidadao\Pages\Auth;

use Filament\Pages\Auth\Register as BaseRegister;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;
use App\Models\Tenant;
use App\Models\Pessoa;
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

                        // CPF e telefone alimentam a Pessoa vinculada (não são colunas de users).
                        // Necessários aos itens 136/147/214/219. Ver processosConceito.md §9.1.
                        TextInput::make('cpf')
                            ->label('CPF')
                            ->required()
                            ->mask('999.999.999-99')
                            ->maxLength(14),
                        TextInput::make('telefone')
                            ->label('Telefone / Celular')
                            ->required()
                            // Máscara híbrida: 8 dígitos (fixo) ou 9 dígitos (celular) — item 5
                            ->mask(RawJs::make(<<<'JS'
                                $input.length > 14 ? '(99) 99999-9999' : '(99) 9999-9999'
                                JS))
                            ->maxLength(20),

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
        // 1. Isolamos os campos que NÃO pertencem à tabela 'users'
        $tenantId = $data['tenant_id'];
        $cpf = $data['cpf'] ?? null;
        $telefone = $data['telefone'] ?? null;
        unset($data['tenant_id'], $data['cpf'], $data['telefone']);

        // 2. Criamos o Usuário normalmente (Padrão do Filament)
        $user = parent::handleRegistration($data);

        // 3. Vincula o cidadão à cidade escolhida (pivot tenant_user)
        $user->tenants()->attach($tenantId);

        // 4. Cria/vincula a Pessoa no tenant escolhido (o cidadão é User + Pessoa).
        //    Dedup por CPF dentro do tenant. tenant_id explícito: o registro roda
        //    SEM tenant no contexto Filament. Ver processosConceito.md §9.1.
        $pessoa = Pessoa::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('cpf', $cpf)
            ->first();

        if ($pessoa) {
            if (empty($pessoa->user_id)) {
                $pessoa->user_id = $user->id;
            }
            if (empty($pessoa->telefone) && $telefone) {
                $pessoa->telefone = $telefone;
            }
            $pessoa->save();
        } else {
            $pessoa = new Pessoa();
            $pessoa->tenant_id = $tenantId;
            $pessoa->user_id = $user->id;
            $pessoa->name = $user->name;
            $pessoa->cpf = $cpf;
            $pessoa->telefone = $telefone;
            $pessoa->type = 'fisica';
            $pessoa->save();
        }

        return $user;
    }
}
