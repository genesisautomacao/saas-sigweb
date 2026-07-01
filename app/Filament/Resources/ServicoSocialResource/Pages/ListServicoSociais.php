<?php

namespace App\Filament\Resources\ServicoSocialResource\Pages;

use App\Filament\Resources\ServicoSocialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServicoSociais extends ListRecords
{
    protected static string $resource = ServicoSocialResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
