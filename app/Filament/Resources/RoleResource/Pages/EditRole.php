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

        // Usamos array_values() para garantir que o Livewire não transforme o Array em Objeto
        // ATENÇÃO: Adicionado o 'view_my_leads' aqui para a caixinha vir marcada se ele tiver a permissão
        $data['permissions_leads'] = array_values(array_intersect($permissions, ['view_leads', 'create_leads', 'edit_leads', 'delete_leads', 'view_my_leads', 'import_leads']));

        $data['permissions_users'] = array_values(array_intersect($permissions, ['view_users', 'create_users', 'edit_users', 'delete_users']));

        $data['permissions_roles'] = array_values(array_intersect($permissions, ['view_roles', 'create_roles', 'edit_roles', 'delete_roles']));

        $data['permissions_sellers'] = array_values(array_intersect($permissions, ['view_sellers', 'create_sellers', 'edit_sellers', 'delete_sellers']));

        $data['permissions_settings'] = array_values(array_intersect($permissions, ['manage_crm_settings']));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extração segura de todas as 5 caixinhas
        $leadsData = $data['permissions_leads'] ?? [];
        $settingsData = $data['permissions_settings'] ?? [];
        $usersData = $data['permissions_users'] ?? [];
        $sellersData = $data['permissions_sellers'] ?? [];
        $rolesData = $data['permissions_roles'] ?? [];

        // Converte o "true" do botão de marcar todos, ou usa o array selecionado
        $leads = is_array($leadsData) ? $leadsData : ($leadsData === true ? ['view_leads', 'create_leads', 'edit_leads', 'delete_leads', 'view_my_leads', 'import_leads'] : []);
        $settings = is_array($settingsData) ? $settingsData : ($settingsData === true ? ['manage_crm_settings'] : []);
        $users = is_array($usersData) ? $usersData : ($usersData === true ? ['view_users', 'create_users', 'edit_users', 'delete_users'] : []);
        $sellers = is_array($sellersData) ? $sellersData : ($sellersData === true ? ['view_sellers', 'create_sellers', 'edit_sellers', 'delete_sellers'] : []);
        $roles = is_array($rolesData) ? $rolesData : ($rolesData === true ? ['view_roles', 'create_roles', 'edit_roles', 'delete_roles'] : []);

        // Junta tudo num array só
        $this->permissionsToSync = array_merge($leads, $settings, $users, $sellers, $roles);

        // Remove do form data para não quebrar a query SQL
        unset(
            $data['permissions_leads'],
            $data['permissions_settings'],
            $data['permissions_users'],
            $data['permissions_sellers'],
            $data['permissions_roles']
        );

        return $data;
    }

    protected function afterSave(): void
    {
        // Aqui a mágica do Spatie acontece, mas agora com o array completo!
        $this->record->syncPermissions($this->permissionsToSync);
    }
}