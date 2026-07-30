<?php

namespace App\Filament\Admin\Resources\UsuarioAdminResource\Pages;

use App\Filament\Admin\Resources\UsuarioAdminResource;
use App\Models\User;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditUsuarioAdmin extends EditRecord
{
    protected static string $resource = UsuarioAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn (User $record) => $record->getKey() === Filament::auth()->id()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['papel_admin'] = $this->record->papelAdmin() ?? User::PAPEL_OPERADOR;
        $data['capacidades'] = $this->record->capacidadesAdmin();

        return $data;
    }

    protected function afterSave(): void
    {
        // Editando a si mesmo, o seletor de papel fica desabilitado — mantém o papel atual.
        $papel = $this->record->getKey() === Filament::auth()->id()
            ? $this->record->papelAdmin()
            : ($this->data['papel_admin'] ?? null);

        $this->record->sincronizarAcessoAdmin($papel, $this->data['capacidades'] ?? []);
    }
}
