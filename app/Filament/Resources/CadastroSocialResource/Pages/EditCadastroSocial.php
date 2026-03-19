<?php

namespace App\Filament\Resources\CadastroSocialResource\Pages;

use App\Filament\Resources\CadastroSocialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCadastroSocial extends EditRecord
{
    protected static string $resource = CadastroSocialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
