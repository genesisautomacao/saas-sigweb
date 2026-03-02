<?php

namespace App\Filament\Resources\LeadStatusResource\Pages;

use App\Filament\Resources\LeadStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLeadStatus extends EditRecord
{
    protected static string $resource = LeadStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
