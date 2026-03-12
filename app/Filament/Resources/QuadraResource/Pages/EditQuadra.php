<?php

namespace App\Filament\Resources\QuadraResource\Pages;

use App\Filament\Resources\QuadraResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQuadra extends EditRecord
{
    protected static string $resource = QuadraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
