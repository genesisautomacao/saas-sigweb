<?php

namespace App\Filament\Resources\LeadPotentialResource\Pages;

use App\Filament\Resources\LeadPotentialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLeadPotential extends EditRecord
{
    protected static string $resource = LeadPotentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
