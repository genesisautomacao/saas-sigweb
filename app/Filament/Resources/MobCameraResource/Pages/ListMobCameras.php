<?php

namespace App\Filament\Resources\MobCameraResource\Pages;

use App\Filament\Resources\Concerns\TemExportacaoMobilidade;
use App\Filament\Resources\MobCameraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMobCameras extends ListRecords
{
    use TemExportacaoMobilidade;

    protected static string $resource = MobCameraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->mobExportActionGroup('mob_camera'),
            Actions\CreateAction::make()->label('Nova Câmera'),
        ];
    }
}
