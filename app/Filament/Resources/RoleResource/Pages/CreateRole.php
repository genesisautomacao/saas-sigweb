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
        // Extração segura de todas as 5 caixinhas
        $leadsData = $data['permissions_leads'] ?? [];
        $settingsData = $data['permissions_settings'] ?? [];
        $usersData = $data['permissions_users'] ?? [];
        $sellersData = $data['permissions_sellers'] ?? [];
        $rolesData = $data['permissions_roles'] ?? [];

        // Converte o "true" (se o usuário clicou em "Select All") ou usa o array selecionado
        $leads = is_array($leadsData) ? $leadsData : ($leadsData === true ? ['view_leads', 'create_leads', 'edit_leads', 'delete_leads', 'view_my_leads', 'import_leads'] : []);
        $settings = is_array($settingsData) ? $settingsData : ($settingsData === true ? ['manage_crm_settings'] : []);
        $users = is_array($usersData) ? $usersData : ($usersData === true ? ['view_users', 'create_users', 'edit_users', 'delete_users'] : []);
        $sellers = is_array($sellersData) ? $sellersData : ($sellersData === true ? ['view_sellers', 'create_sellers', 'edit_sellers', 'delete_sellers'] : []);
        $roles = is_array($rolesData) ? $rolesData : ($rolesData === true ? ['view_roles', 'create_roles', 'edit_roles', 'delete_roles'] : []);

        // Junta tudo em um único array
        $this->permissionsToSync = array_merge($leads, $settings, $users, $sellers, $roles);

        // Remove os arrays fantasmas antes de salvar no banco
        unset(
            $data['permissions_leads'],
            $data['permissions_settings'],
            $data['permissions_users'],
            $data['permissions_sellers'],
            $data['permissions_roles']
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncPermissions($this->permissionsToSync);
    }
}