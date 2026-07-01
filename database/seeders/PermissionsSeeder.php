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

            /* Módulo Administrativo: Pontos Panorâmicos 360º */
            'view_pontos_panoramicos',
            'create_pontos_panoramicos',
            'edit_pontos_panoramicos',
            'delete_pontos_panoramicos',

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

            /* Módulo de Estoque — Cadastros Auxiliares (permissão única "gerenciar" por entidade) */
            'gerenciar_estabelecimentos',
            'gerenciar_fabricantes',
            'gerenciar_fornecedores',
            'gerenciar_unidade_medidas',
            'gerenciar_embalagens',
            'gerenciar_familia_produtos',
            'gerenciar_tipo_estoques',
            'gerenciar_operacao_internas',
            'gerenciar_lote_estoques',

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
            'view_perimetros_urbanos',
            'create_perimetros_urbanos',
            'edit_perimetros_urbanos',
            'delete_perimetros_urbanos',
            'view_meio_fios',
            'create_meio_fios',
            'edit_meio_fios',
            'delete_meio_fios',
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

            /* Módulo BPMN / Processos Digitais */
            'view_bpmn_fluxos', 'create_bpmn_fluxos', 'edit_bpmn_fluxos', 'delete_bpmn_fluxos',
            'view_processos_digitais', 'create_processos_digitais', 'edit_processos_digitais', 'delete_processos_digitais',

            /* Módulo de Consultas de Viabilidade */
            'view_cnaes', 'create_cnaes', 'edit_cnaes', 'delete_cnaes',
            'view_regras_zoneamento', 'create_regras_zoneamento', 'edit_regras_zoneamento', 'delete_regras_zoneamento',
            'view_parametros_urbanos', 'create_parametros_urbanos', 'edit_parametros_urbanos', 'delete_parametros_urbanos',

            /* Módulo PGV / Gestão Tributária */
            'view_pgv_parametros', 'create_pgv_parametros', 'edit_pgv_parametros', 'delete_pgv_parametros',
            'view_setores_fiscais', 'create_setores_fiscais', 'edit_setores_fiscais', 'delete_setores_fiscais',
            'view_lote_valor_historicos', 'create_lote_valor_historicos', 'edit_lote_valor_historicos', 'delete_lote_valor_historicos',

            /* Páginas de Administração */
            'view_auditoria',
            'view_monitoramento_campo',
            'view_produtividade',
            'view_mensagens',

            /* Permissões de visibilidade de camadas GIS */
            'ver_camada_perimetros',
            'ver_camada_setores_fiscais',
            'ver_camada_bairros',
            'ver_camada_loteamentos',
            'ver_camada_quadras',
            'ver_camada_lotes',
            'ver_camada_logradouros',
            'ver_camada_meio_fios',
            'ver_camada_secoes_logradouro',
            'ver_camada_postes',
            'ver_camada_arvores',
            'ver_camada_zonas',
            'ver_camada_patrimonio_publico',
            'ver_camada_cemiterios',
            'ver_camada_rural_localidades',
            'ver_camada_rural_propriedades',
            'ver_camada_rural_estradas',
            'ver_camada_rural_hidrografias',
            'ver_camada_rural_pontes',
            'ver_camada_rural_pontos_interesse',
            'ver_camada_pontos_panoramicos',
            'ver_camada_toponimias',

            /* Permissões da toolbar do mapa */
            'toolbar_criar_artefatos',
            'toolbar_ferramentas',
            'toolbar_filtros',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}
