<?php

namespace App\Filament\Resources\LeadPotentialResource\Pages;

use App\Filament\Resources\LeadPotentialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeadPotentials extends ListRecords
{
    protected static string $resource = LeadPotentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
