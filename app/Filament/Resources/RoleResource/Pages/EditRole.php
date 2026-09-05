<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Support\Modulos;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    public array $permissionsToSync = [];

    /** Reparte as permissões do papel nas caixas (fonte única: RoleResource::caixas()). */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $permissions = $this->record->permissions->pluck('name')->all();

        foreach (RoleResource::caixas() as $chave => $caixa) {
            $data[$chave] = array_values(array_intersect($permissions, array_keys($caixa['opcoes'])));
        }

        return $data;
    }

    /**
     * Junta o que foi marcado nas caixas visíveis e PRESERVA (decisão D3 de
     * docs/Modulos_Permissoes.txt) as permissões de módulos inativos na prefeitura,
     * cujas caixas não foram oferecidas — religar o módulo devolve a configuração.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $marcadas = [];
        foreach (array_keys(RoleResource::caixas()) as $chave) {
            $valor = $data[$chave] ?? [];
            if (is_array($valor)) {
                $marcadas = array_merge($marcadas, $valor);
            }
            unset($data[$chave]);
        }

        $inativas = Modulos::permissoesInativas(Filament::getTenant());
        $preservadas = $this->record->permissions->pluck('name')
            ->filter(fn ($p) => in_array($p, $inativas, true))
            ->all();

        $this->permissionsToSync = array_values(array_unique(array_merge($marcadas, $preservadas)));

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->refresh();

        // Papel que acompanha todos os módulos = conjunto completo dos módulos ativos
        if ($this->record->todos_modulos) {
            $tenant = Filament::getTenant();
            $this->record->syncPermissions($tenant?->permissoesDosModulosAtivos() ?? $this->permissionsToSync);

            return;
        }

        $this->record->syncPermissions($this->permissionsToSync);
    }
}
