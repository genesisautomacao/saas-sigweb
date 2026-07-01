<?php

namespace App\Filament\Resources\InformacaoSocialResource\Pages;

use App\Filament\Resources\InformacaoSocialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInformacaoSociais extends ListRecords
{
    protected static string $resource = InformacaoSocialResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
