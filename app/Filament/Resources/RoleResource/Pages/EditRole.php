<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    public array $permissionsToSync = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $permissions = $this->record->permissions->pluck('name')->toArray();

        $data['permissions_users'] = array_values(array_intersect($permissions, ['view_users', 'create_users', 'edit_users', 'delete_users']));
        $data['permissions_roles'] = array_values(array_intersect($permissions, ['view_roles', 'create_roles', 'edit_roles', 'delete_roles']));
        $data['permissions_pessoas'] = array_values(array_intersect($permissions, ['view_pessoas', 'create_pessoas', 'edit_pessoas', 'delete_pessoas']));
        $data['permissions_contatos'] = array_values(array_intersect($permissions, ['view_contatos', 'create_contatos', 'edit_contatos', 'delete_contatos']));
        $data['permissions_enderecos'] = array_values(array_intersect($permissions, ['view_enderecos', 'create_enderecos', 'edit_enderecos', 'delete_enderecos']));
        $data['permissions_documentos'] = array_values(array_intersect($permissions, ['view_documentos', 'create_documentos', 'edit_documentos', 'delete_documentos']));

        $data['permissions_iluminacao'] = array_values(array_intersect($permissions, ['view_tipos_poste', 'create_tipos_poste', 'edit_tipos_poste', 'delete_tipos_poste', 'view_postes', 'create_postes', 'edit_postes', 'delete_postes']));

        $data['permissions_arborizacao'] = array_values(array_intersect($permissions, ['view_arvores', 'create_arvores', 'edit_arvores', 'delete_arvores']));

        $data['permissions_estoque'] = array_values(array_intersect($permissions, ['view_locais_estoque', 'create_locais_estoque', 'edit_locais_estoque', 'delete_locais_estoque', 'view_marcas', 'create_marcas', 'edit_marcas', 'delete_marcas', 'view_produtos', 'create_produtos', 'edit_produtos', 'delete_produtos', 'view_estoques', 'view_movimentacoes', 'create_movimentacoes', 'edit_movimentacoes', 'delete_movimentacoes']));

        $data['permissions_manutencao'] = array_values(array_intersect($permissions, ['view_solicitacoes', 'create_solicitacoes', 'edit_solicitacoes', 'delete_solicitacoes', 'view_ordens_servico', 'create_ordens_servico', 'edit_ordens_servico', 'delete_ordens_servico']));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $usersData = $data['permissions_users'] ?? [];
        $rolesData = $data['permissions_roles'] ?? [];
        $pessoasData = $data['permissions_pessoas'] ?? [];
        $contatosData = $data['permissions_contatos'] ?? [];
        $enderecosData = $data['permissions_enderecos'] ?? [];
        $documentosData = $data['permissions_documentos'] ?? [];
        $iluminacaoData = $data['permissions_iluminacao'] ?? [];
        $arborizacaoData = $data['permissions_arborizacao'] ?? [];
        $estoqueData = $data['permissions_estoque'] ?? [];
        $manutencaoData = $data['permissions_manutencao'] ?? []; // 🟢 ADICIONADO AQUI

        $users = is_array($usersData) ? $usersData : ($usersData === true ? ['view_users', 'create_users', 'edit_users', 'delete_users'] : []);
        $roles = is_array($rolesData) ? $rolesData : ($rolesData === true ? ['view_roles', 'create_roles', 'edit_roles', 'delete_roles'] : []);
        $pessoas = is_array($pessoasData) ? $pessoasData : ($pessoasData === true ? ['view_pessoas', 'create_pessoas', 'edit_pessoas', 'delete_pessoas'] : []);
        $contatos = is_array($contatosData) ? $contatosData : ($contatosData === true ? ['view_contatos', 'create_contatos', 'edit_contatos', 'delete_contatos'] : []);
        $enderecos = is_array($enderecosData) ? $enderecosData : ($enderecosData === true ? ['view_enderecos', 'create_enderecos', 'edit_enderecos', 'delete_enderecos'] : []);
        $documentos = is_array($documentosData) ? $documentosData : ($documentosData === true ? ['view_documentos', 'create_documentos', 'edit_documentos', 'delete_documentos'] : []);
        $iluminacao = is_array($iluminacaoData) ? $iluminacaoData : ($iluminacaoData === true ? ['view_tipos_poste', 'create_tipos_poste', 'edit_tipos_poste', 'delete_tipos_poste', 'view_postes', 'create_postes', 'edit_postes', 'delete_postes'] : []);
        $arborizacao = is_array($arborizacaoData) ? $arborizacaoData : ($arborizacaoData === true ? ['view_arvores', 'create_arvores', 'edit_arvores', 'delete_arvores'] : []);

        $estoque = is_array($estoqueData) ? $estoqueData : ($estoqueData === true ? ['view_locais_estoque', 'create_locais_estoque', 'edit_locais_estoque', 'delete_locais_estoque', 'view_marcas', 'create_marcas', 'edit_marcas', 'delete_marcas', 'view_produtos', 'create_produtos', 'edit_produtos', 'delete_produtos', 'view_estoques', 'view_movimentacoes', 'create_movimentacoes', 'edit_movimentacoes', 'delete_movimentacoes'] : []);

        $manutencao = is_array($manutencaoData) ? $manutencaoData : ($manutencaoData === true ? ['view_solicitacoes', 'create_solicitacoes', 'edit_solicitacoes', 'delete_solicitacoes', 'view_ordens_servico', 'create_ordens_servico', 'edit_ordens_servico', 'delete_ordens_servico'] : []); // 🟢 ADICIONADO AQUI

        // 🟢 O GRANDE AJUSTE: Colocando $estoque e $manutencao no merge final!
        $this->permissionsToSync = array_merge($users, $roles, $pessoas, $contatos, $enderecos, $documentos, $iluminacao, $arborizacao, $estoque, $manutencao);

        unset(
            $data['permissions_users'],
            $data['permissions_roles'],
            $data['permissions_pessoas'],
            $data['permissions_contatos'],
            $data['permissions_enderecos'],
            $data['permissions_documentos'],
            $data['permissions_iluminacao'],
            $data['permissions_arborizacao'],
            $data['permissions_estoque'],
            $data['permissions_manutencao'] // 🟢 ADICIONADO AQUI
        );

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncPermissions($this->permissionsToSync);
    }
}