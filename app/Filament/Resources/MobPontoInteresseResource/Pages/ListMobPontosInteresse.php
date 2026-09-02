<?php

namespace App\Filament\Resources\MobPontoInteresseResource\Pages;

use App\Filament\Resources\Concerns\TemExportacaoMobilidade;
use App\Filament\Resources\MobPontoInteresseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMobPontosInteresse extends ListRecords
{
    use TemExportacaoMobilidade;

    protected static string $resource = MobPontoInteresseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->mobExportActionGroup('mob_ponto_interesse'),
            Actions\CreateAction::make()->label('Novo Ponto'),
        ];
    }
}
