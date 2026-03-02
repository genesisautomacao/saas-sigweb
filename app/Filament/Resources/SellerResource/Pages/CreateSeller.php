<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Resources\SellerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateSeller extends CreateRecord
{
    protected static string $resource = SellerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = \Filament\Facades\Filament::getTenant();

        // 1. Cria o Usuário de acesso
        $user = \App\Models\User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // 2. Vincula e configura permissões (Multi-Tenant Spatie)
        if ($tenant) {
            $user->tenants()->attach($tenant->id);
            
            // Avisa o Spatie em qual Tenant estamos operando
            setPermissionsTeamId($tenant->id);
            
            if (!empty($data['role_name'])) {
                $user->assignRole($data['role_name']);
            }
        }

        // 3. Informa o ID do usuário recém-criado para o cadastro do Seller
        $data['user_id'] = $user->id;

        // 4. Remove os campos fantasmas do array para não dar erro de SQL na tabela 'sellers'
        unset($data['name'], $data['email'], $data['password'], $data['role_name']);

        return $data;
    }
}
