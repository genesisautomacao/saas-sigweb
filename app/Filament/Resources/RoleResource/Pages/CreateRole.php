<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    public array $permissionsToSync = [];

    /** Junta o que foi marcado nas caixas (fonte única: RoleResource::caixas()). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $marcadas = [];
        foreach (array_keys(RoleResource::caixas()) as $chave) {
            $valor = $data[$chave] ?? [];
            if (is_array($valor)) {
                $marcadas = array_merge($marcadas, $valor);
            }
            unset($data[$chave]);
        }

        $this->permissionsToSync = array_values(array_unique($marcadas));

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->todos_modulos) {
            $tenant = Filament::getTenant();
            $this->record->syncPermissions($tenant?->permissoesDosModulosAtivos() ?? $this->permissionsToSync);

            return;
        }

        $this->record->syncPermissions($this->permissionsToSync);
    }
}
