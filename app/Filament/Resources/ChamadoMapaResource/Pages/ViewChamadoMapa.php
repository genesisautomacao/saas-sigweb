<?php

namespace App\Filament\Resources\ChamadoMapaResource\Pages;

use App\Filament\Resources\ChamadoMapaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewChamadoMapa extends ViewRecord
{
    protected static string $resource = ChamadoMapaResource::class;

    public function getTitle(): string
    {
        return 'Chamado '.$this->record->protocolo;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('alterarSituacao')
                ->label('Alterar situação')->icon('heroicon-o-arrow-path')
                ->modalHeading(fn () => 'Chamado '.$this->record->protocolo)
                ->fillForm(fn () => ChamadoMapaResource::dadosSituacao($this->record))
                ->form(ChamadoMapaResource::formularioSituacao())
                ->action(function (array $data) {
                    ChamadoMapaResource::salvarSituacao($this->record, $data);
                    $this->record->refresh();
                }),
            Actions\Action::make('verNoMapa')
                ->label('Ver no mapa')->icon('heroicon-o-map')->color('gray')
                ->visible(fn () => $this->record->getRawOriginal('geo') !== null)
                ->url(fn () => ChamadoMapaResource::urlNoMapa($this->record), shouldOpenInNewTab: true),
            Actions\DeleteAction::make(),
        ];
    }
}
