<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Lista das ações que existem dentro do seu sistema
        $permissions = [

            /* users */
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            /* roles */
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',

            /* Módulo Administrativo: Pessoas */
            'view_pessoas',
            'create_pessoas',
            'edit_pessoas',
            'delete_pessoas',

            /* Módulo Administrativo: Contatos */
            'view_contatos',
            'create_contatos',
            'edit_contatos',
            'delete_contatos',

            /* Módulo Administrativo: Endereços */
            'view_enderecos',
            'create_enderecos',
            'edit_enderecos',
            'delete_enderecos',

            /* Módulo Administrativo: Documentos */
            'view_documentos',
            'create_documentos',
            'edit_documentos',
            'delete_documentos',

            /* Módulo: Iluminação Pública */
            'view_tipos_poste',
            'create_tipos_poste',
            'edit_tipos_poste',
            'delete_tipos_poste',
            'view_postes',
            'create_postes',
            'edit_postes',
            'delete_postes',

            /* Módulo de Arborização */
            'view_arvores',
            'create_arvores',
            'edit_arvores',
            'delete_arvores'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}