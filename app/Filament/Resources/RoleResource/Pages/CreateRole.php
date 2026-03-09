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
        $iluminacaoData = $data['permissions_iluminacao'] ?? [];
        $arborizacaoData = $data['permissions_arborizacao'] ?? []; // 🛑 NOVO

        $users = is_array($usersData) ? $usersData : ($usersData === true ? ['view_users', 'create_users', 'edit_users', 'delete_users'] : []);
        $roles = is_array($rolesData) ? $rolesData : ($rolesData === true ? ['view_roles', 'create_roles', 'edit_roles', 'delete_roles'] : []);
        $pessoas = is_array($pessoasData) ? $pessoasData : ($pessoasData === true ? ['view_pessoas', 'create_pessoas', 'edit_pessoas', 'delete_pessoas'] : []);
        $contatos = is_array($contatosData) ? $contatosData : ($contatosData === true ? ['view_contatos', 'create_contatos', 'edit_contatos', 'delete_contatos'] : []);
        $enderecos = is_array($enderecosData) ? $enderecosData : ($enderecosData === true ? ['view_enderecos', 'create_enderecos', 'edit_enderecos', 'delete_enderecos'] : []);
        $documentos = is_array($documentosData) ? $documentosData : ($documentosData === true ? ['view_documentos', 'create_documentos', 'edit_documentos', 'delete_documentos'] : []);
        $iluminacao = is_array($iluminacaoData) ? $iluminacaoData : ($iluminacaoData === true ? ['view_tipos_poste', 'create_tipos_poste', 'edit_tipos_poste', 'delete_tipos_poste', 'view_postes', 'create_postes', 'edit_postes', 'delete_postes'] : []);
        $arborizacao = is_array($arborizacaoData) ? $arborizacaoData : ($arborizacaoData === true ? ['view_arvores', 'create_arvores', 'edit_arvores', 'delete_arvores'] : []); // 🛑 NOVO

        $this->permissionsToSync = array_merge($users, $roles, $pessoas, $contatos, $enderecos, $documentos, $iluminacao, $arborizacao);

        unset(
            $data['permissions_users'],
            $data['permissions_roles'],
            $data['permissions_pessoas'],
            $data['permissions_contatos'],
            $data['permissions_enderecos'],
            $data['permissions_documentos'],
            $data['permissions_iluminacao'],
            $data['permissions_arborizacao'] // 🛑 NOVO
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncPermissions($this->permissionsToSync);
    }
}