<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    public array $permissionsToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $usersData = $data['permissions_users'] ?? [];
        $rolesData = $data['permissions_roles'] ?? [];
        $pessoasData = $data['permissions_pessoas'] ?? [];
        $contatosData = $data['permissions_contatos'] ?? [];
        $enderecosData = $data['permissions_enderecos'] ?? [];
        $documentosData = $data['permissions_documentos'] ?? [];
        $pontosPanoramicosData = $data['permissions_pontos_panoramicos'] ?? [];
        $iluminacaoData = $data['permissions_iluminacao'] ?? [];
        $arborizacaoData = $data['permissions_arborizacao'] ?? []; 
        $estoqueData = $data['permissions_estoque'] ?? [];
        $manutencaoData = $data['permissions_manutencao'] ?? []; 
        $cemiterioData = $data['permissions_cemiterio'] ?? [];
        $imobiliarioData = $data['permissions_imobiliario'] ?? [];
        $socialData = $data['permissions_social'] ?? [];
        $ruralData = $data['permissions_rural'] ?? [];
        $patrimonioData      = $data['permissions_patrimonio'] ?? [];
        $bpmnData            = $data['permissions_bpmn'] ?? [];
        $viabilidadeData     = $data['permissions_viabilidade'] ?? [];
        $pgvData             = $data['permissions_pgv'] ?? [];
        $administracaoData   = $data['permissions_administracao'] ?? [];
        $mapaCamadasData     = $data['permissions_mapa_camadas'] ?? [];
        $mapaToolbarData     = $data['permissions_mapa_toolbar'] ?? [];

        $users = is_array($usersData) ? $usersData : ($usersData === true ? ['view_users', 'create_users', 'edit_users', 'delete_users'] : []);
        $roles = is_array($rolesData) ? $rolesData : ($rolesData === true ? ['view_roles', 'create_roles', 'edit_roles', 'delete_roles'] : []);
        $pessoas = is_array($pessoasData) ? $pessoasData : ($pessoasData === true ? ['view_pessoas', 'create_pessoas', 'edit_pessoas', 'delete_pessoas'] : []);
        $contatos = is_array($contatosData) ? $contatosData : ($contatosData === true ? ['view_contatos', 'create_contatos', 'edit_contatos', 'delete_contatos'] : []);
        $enderecos = is_array($enderecosData) ? $enderecosData : ($enderecosData === true ? ['view_enderecos', 'create_enderecos', 'edit_enderecos', 'delete_enderecos'] : []);
        $documentos = is_array($documentosData) ? $documentosData : ($documentosData === true ? ['view_documentos', 'create_documentos', 'edit_documentos', 'delete_documentos'] : []);
        $pontosPanoramicos = is_array($pontosPanoramicosData) ? $pontosPanoramicosData : ($pontosPanoramicosData === true ? ['view_pontos_panoramicos', 'create_pontos_panoramicos', 'edit_pontos_panoramicos', 'delete_pontos_panoramicos'] : []);
        $iluminacao = is_array($iluminacaoData) ? $iluminacaoData : ($iluminacaoData === true ? ['view_tipos_poste', 'create_tipos_poste', 'edit_tipos_poste', 'delete_tipos_poste', 'view_postes', 'create_postes', 'edit_postes', 'delete_postes'] : []);
        $arborizacao = is_array($arborizacaoData) ? $arborizacaoData : ($arborizacaoData === true ? ['view_arvores', 'create_arvores', 'edit_arvores', 'delete_arvores'] : []);
        $estoque = is_array($estoqueData) ? $estoqueData : ($estoqueData === true ? ['view_locais_estoque', 'create_locais_estoque', 'edit_locais_estoque', 'delete_locais_estoque', 'view_marcas', 'create_marcas', 'edit_marcas', 'delete_marcas', 'view_produtos', 'create_produtos', 'edit_produtos', 'delete_produtos', 'view_estoques', 'create_estoques', 'edit_estoques', 'delete_estoques', 'view_movimentacoes', 'create_movimentacoes', 'edit_movimentacoes', 'delete_movimentacoes'] : []);
        $manutencao = is_array($manutencaoData) ? $manutencaoData : ($manutencaoData === true ? ['view_solicitacoes', 'create_solicitacoes', 'edit_solicitacoes', 'delete_solicitacoes', 'view_ordens_servico', 'create_ordens_servico', 'edit_ordens_servico', 'delete_ordens_servico'] : []);
        $cemiterio = is_array($cemiterioData) ? $cemiterioData : ($cemiterioData === true ? ['view_cemiterios', 'create_cemiterios', 'edit_cemiterios', 'delete_cemiterios', 'view_quadras_cemiterio', 'create_quadras_cemiterio', 'edit_quadras_cemiterio', 'delete_quadras_cemiterio', 'view_logradouros_cemiterio', 'create_logradouros_cemiterio', 'edit_logradouros_cemiterio', 'delete_logradouros_cemiterio', 'view_jazigos', 'create_jazigos', 'edit_jazigos', 'delete_jazigos'] : []);
        $imobiliario = is_array($imobiliarioData) ? $imobiliarioData : ($imobiliarioData === true ? ['view_lotes', 'create_lotes', 'edit_lotes', 'delete_lotes', 'view_logradouros', 'create_logradouros', 'edit_logradouros', 'delete_logradouros', 'view_bairros', 'create_bairros', 'edit_bairros', 'delete_bairros', 'view_loteamentos', 'create_loteamentos', 'edit_loteamentos', 'delete_loteamentos', 'view_quadras', 'create_quadras', 'edit_quadras', 'delete_quadras', 'view_zonas', 'create_zonas', 'edit_zonas', 'delete_zonas'] : []);
        $social = is_array($socialData) ? $socialData : ($socialData === true ? ['view_cadastros_sociais', 'create_cadastros_sociais', 'edit_cadastros_sociais', 'delete_cadastros_sociais'] : []);
        $rural = is_array($ruralData) ? $ruralData : ($ruralData === true ? ['view_rural_localidades', 'create_rural_localidades', 'edit_rural_localidades', 'delete_rural_localidades', 'view_rural_propriedades', 'create_rural_propriedades', 'edit_rural_propriedades', 'delete_rural_propriedades', 'view_rural_estradas', 'create_rural_estradas', 'edit_rural_estradas', 'delete_rural_estradas', 'view_rural_hidrografias', 'create_rural_hidrografias', 'edit_rural_hidrografias', 'delete_rural_hidrografias', 'view_rural_pontes', 'create_rural_pontes', 'edit_rural_pontes', 'delete_rural_pontes', 'view_rural_pontos_interesse', 'create_rural_pontos_interesse', 'edit_rural_pontos_interesse', 'delete_rural_pontos_interesse'] : []);
        
        $patrimonio    = is_array($patrimonioData)    ? $patrimonioData    : ($patrimonioData    === true ? ['view_tipo_patrimonios', 'create_tipo_patrimonios', 'edit_tipo_patrimonios', 'delete_tipo_patrimonios', 'view_patrimonio_publicos', 'create_patrimonio_publicos', 'edit_patrimonio_publicos', 'delete_patrimonio_publicos'] : []);
        $bpmn          = is_array($bpmnData)          ? $bpmnData          : [];
        $viabilidade   = is_array($viabilidadeData)   ? $viabilidadeData   : [];
        $pgv           = is_array($pgvData)           ? $pgvData           : [];
        $administracao = is_array($administracaoData) ? $administracaoData : [];
        $mapaCamadas   = is_array($mapaCamadasData)   ? $mapaCamadasData   : [];
        $mapaToolbar   = is_array($mapaToolbarData)   ? $mapaToolbarData   : [];

        $this->permissionsToSync = array_merge($users, $roles, $pessoas, $contatos, $enderecos, $documentos, $pontosPanoramicos, $iluminacao, $arborizacao, $estoque, $manutencao, $cemiterio, $imobiliario, $social, $rural, $patrimonio, $bpmn, $viabilidade, $pgv, $administracao, $mapaCamadas, $mapaToolbar);

        unset(
            $data['permissions_users'], $data['permissions_roles'], $data['permissions_pessoas'],
            $data['permissions_contatos'], $data['permissions_enderecos'], $data['permissions_documentos'],
            $data['permissions_pontos_panoramicos'],
            $data['permissions_iluminacao'], $data['permissions_arborizacao'], $data['permissions_estoque'],
            $data['permissions_manutencao'], $data['permissions_cemiterio'], $data['permissions_imobiliario'],
            $data['permissions_social'], $data['permissions_rural'], $data['permissions_patrimonio'],
            $data['permissions_bpmn'], $data['permissions_viabilidade'], $data['permissions_pgv'],
            $data['permissions_administracao'], $data['permissions_mapa_camadas'], $data['permissions_mapa_toolbar']
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncPermissions($this->permissionsToSync);
    }
}