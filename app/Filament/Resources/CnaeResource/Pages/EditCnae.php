<?php

namespace App\Filament\Resources\CnaeResource\Pages;

use App\Filament\Resources\CnaeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCnae extends EditRecord
{
    protected static string $resource = CnaeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
