<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndMasterUserSeeder extends Seeder
{
    public function run(): void
    {
        // Cria os papéis principais que combinamos
        // Ao passar tenant_id => null, dizemos ao Spatie que esses são papéis globais do sistema
        $masterRole = Role::firstOrCreate(['name' => 'Master', 'tenant_id' => null]);

        $masterUser = User::firstOrCreate(
            ['email' => 'contato@genesisautomacao.com.br'], // Mude para o seu e-mail de acesso
            [
                'name' => 'Master Admin',
                'password' => Hash::make('sj@240637'), // Mude para uma senha segura
                'email_verified_at' => now(),
            ]
        );

        // Atribui o papel de Master ao usuário
        $masterUser->assignRole($masterRole);
    }
}