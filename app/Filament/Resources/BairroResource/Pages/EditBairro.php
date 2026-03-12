<?php

namespace App\Filament\Resources\BairroResource\Pages;

use App\Filament\Resources\BairroResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBairro extends EditRecord
{
    protected static string $resource = BairroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
