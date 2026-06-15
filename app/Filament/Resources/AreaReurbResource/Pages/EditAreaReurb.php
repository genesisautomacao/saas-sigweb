<?php

namespace App\Filament\Resources\AreaReurbResource\Pages;

use App\Filament\Resources\AreaReurbResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAreaReurb extends EditRecord
{
    protected static string $resource = AreaReurbResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
