<?php

namespace App\Filament\Resources\PerimetroUrbanoResource\Pages;

use App\Filament\Resources\PerimetroUrbanoResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;

class ListPerimetrosUrbanos extends ListRecords
{
    protected static string $resource = PerimetroUrbanoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('novo_distrito')
                ->label('Novo Distrito / Limite')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->modalHeading('Forma de Cadastro Geográfico')
                ->modalDescription('Como deseja registrar a delimitação deste Distrito / Limite?')
                ->modalSubmitActionLabel('Continuar')
                ->modalWidth('md')
                ->form([
                    Forms\Components\Radio::make('metodo')
                        ->hiddenLabel()
                        ->options([
                            'mapa'    => '🗺️ Desenhar o Polígono no Mapa Interativo',
                            'geojson' => '💻 Preencher Manualmente / Importar GeoJSON',
                        ])
                        ->default('mapa')
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($data['metodo'] === 'mapa') {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        return redirect()->to(url('/app/' . $tenant->slug . '/mapa-interativo?layer=perimetros&action=create'));
                    }
                    return redirect()->to(PerimetroUrbanoResource::getUrl('create'));
                }),
        ];
    }
}
