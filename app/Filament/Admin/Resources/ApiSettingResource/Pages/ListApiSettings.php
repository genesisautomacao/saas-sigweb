<?php

namespace App\Filament\Admin\Resources\ApiSettingResource\Pages;

use App\Filament\Admin\Resources\ApiSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApiSettings extends ListRecords
{
    protected static string $resource = ApiSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
