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
            'view_locais_estoque',
            'create_locais_estoque',
            'edit_locais_estoque',
            'delete_locais_estoque',
            'view_marcas',
            'create_marcas',
            'edit_marcas',
            'delete_marcas',
            'view_produtos',
            'create_produtos',
            'edit_produtos',
            'delete_produtos',
            'view_estoques',
            'view_movimentacoes',
            'create_movimentacoes',
            'edit_movimentacoes',
            'delete_movimentacoes',

            /* Módulo de Manutenção e Serviços */
            'view_solicitacoes',
            'create_solicitacoes',
            'edit_solicitacoes',
            'delete_solicitacoes',
            'view_ordens_servico',
            'create_ordens_servico',
            'edit_ordens_servico',
            'delete_ordens_servico',

            /* Módulo de Cemitérios */
            'view_cemiterios',
            'create_cemiterios',
            'edit_cemiterios',
            'delete_cemiterios',
            'view_quadras_cemiterio',
            'create_quadras_cemiterio',
            'edit_quadras_cemiterio',
            'delete_quadras_cemiterio',
            'view_logradouros_cemiterio',
            'create_logradouros_cemiterio',
            'edit_logradouros_cemiterio',
            'delete_logradouros_cemiterio',
            'view_jazigos',
            'create_jazigos',
            'edit_jazigos',
            'delete_jazigos',

            /* Módulo Imobiliário / Geográfico */
            'view_lotes',
            'create_lotes',
            'edit_lotes',
            'delete_lotes',
            'view_logradouros',
            'create_logradouros',
            'edit_logradouros',
            'delete_logradouros',
            'view_bairros',
            'create_bairros',
            'edit_bairros',
            'delete_bairros',
            'view_loteamentos',
            'create_loteamentos',
            'edit_loteamentos',
            'delete_loteamentos',
            'view_quadras',
            'create_quadras',
            'edit_quadras',
            'delete_quadras',
            'view_zonas',
            'create_zonas',
            'edit_zonas',
            'delete_zonas',

            /* Módulo de Cadastro Social */
            'view_cadastros_sociais',
            'create_cadastros_sociais',
            'edit_cadastros_sociais',
            'delete_cadastros_sociais',

            /* Faltou o módulo de PGV */

            /* Faltou o Módulo de Processos BPMN */

            /* Módulo de Cadastro Rural */
            'view_rural_localidades',
            'create_rural_localidades',
            'edit_rural_localidades',
            'delete_rural_localidades',
            'view_rural_propriedades',
            'create_rural_propriedades',
            'edit_rural_propriedades',
            'delete_rural_propriedades',
            'view_rural_estradas',
            'create_rural_estradas',
            'edit_rural_estradas',
            'delete_rural_estradas',
            'view_rural_hidrografias',
            'create_rural_hidrografias',
            'edit_rural_hidrografias',
            'delete_rural_hidrografias',
            'view_rural_pontes',
            'create_rural_pontes',
            'edit_rural_pontes',
            'delete_rural_pontes',
            'view_rural_pontos_interesse',
            'create_rural_pontos_interesse',
            'edit_rural_pontos_interesse',
            'delete_rural_pontos_interesse',

            /* Módulo de Patrimônios Públicos */
            'view_tipo_patrimonios', 'create_tipo_patrimonios', 'edit_tipo_patrimonios', 'delete_tipo_patrimonios',
            'view_patrimonio_publicos', 'create_patrimonio_publicos', 'edit_patrimonio_publicos', 'delete_patrimonio_publicos',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}
