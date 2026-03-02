<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    // Este método roda DEPOIS que o usuário é salvo no banco
    protected function afterCreate(): void
    {
        // Pega a Tenant atual que o Manager está logado
        $tenant = Filament::getTenant();
        
        // Pega o usuário que acabou de ser criado no formulário
        $user = $this->record;

        // Anexa o usuário à Tenant na tabela pivô
        if ($tenant) {
            $user->tenants()->attach($tenant->id);
        }
    }
}