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
            'delete_arvores',

            /* Módulo de Estoque */
            'view_locais_estoque', 'create_locais_estoque', 'edit_locais_estoque', 'delete_locais_estoque',
            'view_marcas', 'create_marcas', 'edit_marcas', 'delete_marcas',
            'view_produtos', 'create_produtos', 'edit_produtos', 'delete_produtos',
            'view_estoques',
            'view_movimentacoes', 'create_movimentacoes', 'edit_movimentacoes', 'delete_movimentacoes',

            /* Módulo de Manutenção e Serviços */
            'view_solicitacoes', 'create_solicitacoes', 'edit_solicitacoes', 'delete_solicitacoes',
            'view_ordens_servico', 'create_ordens_servico', 'edit_ordens_servico', 'delete_ordens_servico'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}