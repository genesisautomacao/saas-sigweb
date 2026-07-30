<?php

namespace App\Filament\Admin\Resources\UsuarioAdminResource\Pages;

use App\Filament\Admin\Resources\UsuarioAdminResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsuarioAdmins extends ListRecords
{
    protected static string $resource = UsuarioAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Novo usuário'),
        ];
    }
}
